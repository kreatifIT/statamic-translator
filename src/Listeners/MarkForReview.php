<?php

namespace Kreatif\Translator\Listeners;

use ElSchneider\MagicTranslator\Events\AfterEntryTranslation;
use Kreatif\Translator\Context\TranslationContext;
use Kreatif\Translator\Support\ReviewFlag;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Support\Str;

/**
 * Markiert maschinell übersetzte Lokalisierungen zur Prüfung und legt neue
 * Lokalisierungen als Entwurf an. $event->entry ist die Ziel-Lokalisierung
 * vor dem Speichern; $event->translatedData wird von Magic Translator als
 * Daten gesetzt.
 */
class MarkForReview
{
    public function handle(AfterEntryTranslation $event): void
    {
        if (! ReviewFlag::enabled()) {
            return;
        }

        $entry = $event->entry;

        if (ReviewFlag::collectionExcluded($entry->collectionHandle())) {
            return;
        }

        $isNew = EntryFacade::find($entry->id()) === null;
        $ctx = app(TranslationContext::class);

        $event->translatedData[ReviewFlag::field()] = true;
        $event->translatedData[ReviewFlag::timestampField()] = now()->toIso8601String();
        $event->translatedData[ReviewFlag::sourceField()] = $ctx->active && $ctx->sourceSite !== ''
            ? $ctx->sourceSite
            : ($entry->hasOrigin() ? $entry->root()?->locale() : null);

        if (($isNew && config('statamic-translator.review.unpublish_new', true))
            || (! $isNew && config('statamic-translator.review.unpublish_existing', false))) {
            $entry->published(false);
        }

        if ($isNew && config('statamic-translator.slug.generate_for_new', true)) {
            $title = $event->translatedData['title'] ?? null;

            if (is_string($title) && trim($title) !== '') {
                $entry->slug(Str::slug($title));
            }
        }
    }
}
