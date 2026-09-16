<?php

namespace Kreatif\Translator\Context;

use Illuminate\Support\Str;
use Kreatif\Translator\Support\ReviewFlag;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Addon;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Throwable;

/**
 * Baut den Übersetzungskontext aus Addon-Settings, Unternehmens-Global und
 * dem Umfeld des Eintrags (Geschwister, bestehende Übersetzung).
 */
final class ContextBuilder
{
    public function __construct(private readonly TranslationContext $ctx) {}

    public function build(Entry $source, string $targetSite): TranslationContext
    {
        $ctx = $this->ctx;
        $ctx->reset();

        $ctx->active = true;
        $ctx->sourceSite = $source->locale();
        $ctx->targetSite = $targetSite;
        $ctx->siteName = $this->siteName($targetSite);

        $this->applyEntryContext($source, $targetSite);
        $this->applySettings($targetSite);

        return $ctx;
    }

    private function siteName(string $targetSite): string
    {
        $handle = config('statamic-translator.company_global.handle', 'company_details');
        $field = config('statamic-translator.company_global.name_field', 'name');

        try {
            if ($set = GlobalSet::findByHandle($handle)) {
                $name = $set->in($targetSite)?->get($field) ?: $set->inDefaultSite()?->get($field);

                if (is_string($name) && $name !== '') {
                    return $name;
                }
            }
        } catch (Throwable) {
            // Global fehlt oder ist nicht lesbar — Fallback unten.
        }

        return (string) config('app.name', '');
    }

    private function applyEntryContext(Entry $source, string $targetSite): void
    {
        $ctx = $this->ctx;
        $root = $source->hasOrigin() ? $source->root() : $source;

        $ctx->collectionTitle = (string) ($source->collection()?->title() ?? '');
        $ctx->blueprintTitle = (string) ($source->blueprint()?->title() ?? '');
        $ctx->entryTitle = (string) ($source->get('title') ?? '');
        $ctx->entryUrl = $source->url();

        $existing = $root->in($targetSite);
        $ctx->isNewLocalization = $existing === null;

        try {
            $parent = $source->parent();

            if ($parent) {
                $ctx->parentTitle = (string) ($parent->in($targetSite)?->get('title') ?? $parent->get('title') ?? '');
            }
        } catch (Throwable) {
            // Keine Struktur — kein Parent.
        }

        if ($existing) {
            $ctx->existingTranslation = $this->excerpt($existing, (int) config('statamic-translator.context.existing_translation_chars', 800));
        }

        $ctx->siblingTitles = $this->siblingTitles($source, $targetSite, (int) config('statamic-translator.context.sibling_titles', 12));
    }

    /**
     * Auszug bereits übersetzter Textfelder (Titel + längere Strings auf oberster Ebene).
     *
     * @return array<string, string>
     */
    private function excerpt(Entry $entry, int $maxChars): array
    {
        $result = [];
        $budget = max(0, $maxChars);

        foreach ($entry->data()->all() as $key => $value) {
            if ($budget <= 0) {
                break;
            }

            if (! is_string($value) || Str::length(trim($value)) < 3) {
                continue;
            }

            if ($key !== 'title' && Str::length($value) < 30) {
                continue;
            }

            if (in_array($key, ['magic_translator', ReviewFlag::field(), ReviewFlag::timestampField(), ReviewFlag::sourceField()], true)) {
                continue;
            }

            $text = Str::limit(trim(strip_tags($value)), min($budget, 400), '…');
            $result[(string) $key] = $text;
            $budget -= Str::length($text);
        }

        return $result;
    }

    /**
     * Bereits übersetzte Titel aus derselben Collection (Quelle → Ziel) — sichert
     * konsistente Terminologie über Seiten hinweg.
     *
     * @return array<int, array{source: string, target: string}>
     */
    private function siblingTitles(Entry $source, string $targetSite, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $pairs = [];

        try {
            $siblings = EntryFacade::query()
                ->where('collection', $source->collectionHandle())
                ->where('site', $source->locale())
                ->where('id', '!=', $source->id())
                ->limit(80)
                ->get();

            foreach ($siblings as $sibling) {
                $target = $sibling->in($targetSite);

                if ($target === null || ReviewFlag::isSet($target)) {
                    continue;
                }

                $from = (string) ($sibling->get('title') ?? '');
                $to = (string) ($target->get('title') ?? '');

                if ($from === '' || $to === '' || $from === $to) {
                    continue;
                }

                $pairs[] = ['source' => $from, 'target' => $to];

                if (count($pairs) >= $limit) {
                    break;
                }
            }
        } catch (Throwable) {
            return $pairs;
        }

        return $pairs;
    }

    private function applySettings(string $targetSite): void
    {
        $ctx = $this->ctx;

        try {
            $settings = Addon::get('kreatif/statamic-translator')?->settings()->all() ?? [];
        } catch (Throwable) {
            $settings = [];
        }

        $ctx->companyDescription = $this->str($settings['company_description'] ?? '');
        $ctx->toneOfVoice = $this->str($settings['tone_of_voice'] ?? '');
        $ctx->targetAudience = $this->str($settings['target_audience'] ?? '');
        $ctx->styleRules = $this->str($settings['style_rules'] ?? '');

        foreach ((array) ($settings['language_rules'] ?? []) as $row) {
            if (($row['site'] ?? null) !== $targetSite) {
                continue;
            }

            $ctx->formality = $this->str($row['formality'] ?? '');
            $ctx->languageRules = $this->str($row['rules'] ?? '');
            break;
        }

        $column = 'translation_'.$targetSite;
        $max = (int) config('statamic-translator.context.glossary_max', 150);

        foreach ((array) ($settings['glossary'] ?? []) as $row) {
            $term = $this->str($row['term'] ?? '');
            $translation = $this->str($row[$column] ?? '');

            if ($term === '' || $translation === '') {
                continue;
            }

            $ctx->glossary[] = ['term' => $term, 'translation' => $translation, 'note' => $this->str($row['note'] ?? '')];

            if (count($ctx->glossary) >= $max) {
                break;
            }
        }

        $ctx->doNotTranslate = collect((array) ($settings['do_not_translate'] ?? []))
            ->map(fn ($v) => $this->str($v))
            ->filter()
            ->values()
            ->all();

        foreach ((array) ($settings['examples'] ?? []) as $row) {
            if (($row['target_site'] ?? null) !== $targetSite) {
                continue;
            }

            $from = $this->str($row['source'] ?? '');
            $to = $this->str($row['target'] ?? '');

            if ($from !== '' && $to !== '') {
                $ctx->examples[] = ['source' => $from, 'target' => $to];
            }
        }

        // Sprachnamen für den Prompt (Site-Namen sind sprechender als Locale-Codes).
        $ctx->sourceSite = Site::get($ctx->sourceSite)?->handle() ?? $ctx->sourceSite;
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
