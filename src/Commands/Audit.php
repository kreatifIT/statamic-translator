<?php

namespace Kreatif\Translator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Throwable;

/**
 * kreatif:translator:audit — findet Felder, die von der KI-Übersetzung
 * fälschlich erfasst würden (URLs, Telefonnummern, Codes) oder die als
 * strukturierte Felder unnötig je Sprache gepflegt werden.
 *
 *  A  localizable: true bei nicht-sprachlichen Typen (assets, select, toggle, …)
 *  B  text/textarea/link-Feld ohne `translatable: false`, das keine Sprache trägt
 */
class Audit extends Command
{
    protected $signature = 'kreatif:translator:audit
        {--json : Ergebnis als JSON ausgeben}
        {--strict : Exit-Code 1, wenn Befunde vorliegen}';

    protected $description = 'Prüft Blueprints auf Felder, die nicht (oder unnötig) übersetzt würden';

    private const STRUCTURED_TYPES = [
        'assets', 'entries', 'terms', 'users', 'select', 'button_group', 'toggle', 'radio',
        'checkboxes', 'integer', 'float', 'date', 'time', 'color', 'video', 'form', 'structures',
        'collections', 'taxonomies', 'sites', 'code', 'dictionary', 'icon', 'range', 'width',
    ];

    private const TEXT_TYPES = ['text', 'textarea', 'link'];

    private const NON_LINGUISTIC_INPUT = ['url', 'email', 'tel', 'number', 'password', 'date', 'time'];

    private const NON_LINGUISTIC_HANDLE = '/(^|_)(url|uri|link|href|mail|email|tel|phone|mobile|fax|sms|whatsapp|code|class|css|icon|ean|sku|gtin|anchor|id|key|token|hex|color|colour|api|number|no|nr|iban|vat|piva|width|height|ratio|embed|video|youtube|vimeo|map|maps|lat|lng|lon|linkedin|facebook|instagram|tiktok)(_|$)/i';

    /** @var array<int, array{severity: string, blueprint: string, path: string, type: string, hint: string}> */
    private array $findings = [];

    public function handle(): int
    {
        foreach ($this->blueprints() as [$label, $blueprint]) {
            try {
                $this->walk($blueprint->fields()->all()->all(), $label, '', false);
            } catch (Throwable $e) {
                $this->findings[] = [
                    'severity' => 'ERR',
                    'blueprint' => $label,
                    'path' => '',
                    'type' => '',
                    'hint' => 'Blueprint nicht lesbar: '.$e->getMessage(),
                ];
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($this->findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } elseif ($this->findings === []) {
            $this->info('Keine Befunde — alle Textfelder sind sprachlich, strukturierte Felder sind nicht lokalisierbar.');
        } else {
            $this->table(['', 'Blueprint', 'Feld', 'Typ', 'Hinweis'], array_map(fn ($f) => array_values($f), $this->findings));
            $this->newLine();
            $this->line('A = localizable: true bei strukturiertem Typ → im Blueprint `localizable: false` setzen (Peak-Primitiven per `config:` überschreiben).');
            $this->line('B = Textfeld ohne sprachlichen Inhalt → `translatable: false` setzen (wirkt in jeder Verschachtelungstiefe).');
            $this->line('Hinweis: Innerhalb von Replicator/Grid/Bard hat `localizable` keine Wirkung — dort zählt nur `translatable: false`.');
        }

        return ($this->option('strict') && $this->findings !== []) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Generator<int, array{0: string, 1: Blueprint}>
     */
    private function blueprints(): \Generator
    {
        foreach (Collection::all() as $collection) {
            foreach ($collection->entryBlueprints() as $blueprint) {
                yield ["collections/{$collection->handle()}/{$blueprint->handle()}", $blueprint];
            }
        }

        foreach (Taxonomy::all() as $taxonomy) {
            foreach ($taxonomy->termBlueprints() as $blueprint) {
                yield ["taxonomies/{$taxonomy->handle()}/{$blueprint->handle()}", $blueprint];
            }
        }

        foreach (GlobalSet::all() as $set) {
            if ($blueprint = $set->blueprint()) {
                yield ["globals/{$set->handle()}", $blueprint];
            }
        }
    }

    /**
     * @param  array<int, Field>  $fields
     */
    private function walk(array $fields, string $label, string $prefix, bool $nested): void
    {
        foreach ($fields as $field) {
            $config = $field->config();
            $type = (string) ($config['type'] ?? '');
            $handle = $field->handle();
            $path = $prefix === '' ? $handle : "{$prefix}.{$handle}";
            $localizable = ($config['localizable'] ?? false) === true;
            $translatable = ($config['translatable'] ?? true) !== false;

            // A — strukturierte Felder, die je Sprache getrennt gepflegt würden
            if (! $nested && $localizable && in_array($type, self::STRUCTURED_TYPES, true)) {
                $this->add('A', $label, $path, $type, 'strukturiert, aber localizable: true');
            }

            // B — Textfelder ohne sprachlichen Inhalt, die zur KI geschickt würden
            $reachable = $nested ? $translatable : ($localizable && $translatable);

            if ($reachable && in_array($type, self::TEXT_TYPES, true) && $this->looksNonLinguistic($handle, $type, $config)) {
                $this->add('B', $label, $path, $type, $this->reason($handle, $type, $config));
            }

            // Rekursion in Container (nur wenn sie überhaupt übersetzt würden)
            $descend = $nested ? $translatable : ($localizable && $translatable);

            if (! $descend) {
                continue;
            }

            foreach ($this->childFields($config) as $setPath => $childFields) {
                $this->walk($childFields, $label, $setPath === '' ? $path : "{$path}.{$setPath}", true);
            }
        }
    }

    /**
     * @return array<string, array<int, Field>>
     */
    private function childFields(array $config): array
    {
        $type = $config['type'] ?? '';
        $result = [];

        if (in_array($type, ['replicator', 'bard'], true)) {
            foreach ((array) ($config['sets'] ?? []) as $groupOrSet => $definition) {
                $sets = isset($definition['sets']) ? $definition['sets'] : [$groupOrSet => $definition];

                foreach ($sets as $setHandle => $set) {
                    $result[(string) $setHandle] = $this->resolve($set['fields'] ?? []);
                }
            }
        } elseif (in_array($type, ['grid', 'group'], true)) {
            $result[''] = $this->resolve($config['fields'] ?? []);
        }

        return $result;
    }

    /**
     * @return array<int, Field>
     */
    private function resolve(array $items): array
    {
        try {
            return (new Fields($items))->all()->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function looksNonLinguistic(string $handle, string $type, array $config): bool
    {
        if ($type === 'link') {
            return true;
        }

        if (in_array($config['input_type'] ?? 'text', self::NON_LINGUISTIC_INPUT, true)) {
            return true;
        }

        foreach ((array) ($config['validate'] ?? []) as $rule) {
            if (is_string($rule) && preg_match('/^(url|email|numeric|integer|regex:|active_url|ip|uuid)/', $rule)) {
                return true;
            }
        }

        return (bool) preg_match(self::NON_LINGUISTIC_HANDLE, $handle);
    }

    private function reason(string $handle, string $type, array $config): string
    {
        if ($type === 'link') {
            return 'link-Feld (URL/Entry) → translatable: false';
        }

        if (($config['input_type'] ?? 'text') !== 'text') {
            return 'input_type '.$config['input_type'].' → translatable: false';
        }

        return 'Handle deutet auf technischen Inhalt → translatable: false (oder umbenennen)';
    }

    private function add(string $severity, string $blueprint, string $path, string $type, string $hint): void
    {
        if ($this->ignored($blueprint, $path)) {
            return;
        }

        $this->findings[] = compact('severity', 'blueprint', 'path', 'type', 'hint');
    }

    private function ignored(string $blueprint, string $path): bool
    {
        foreach ((array) config('statamic-translator.audit.ignore', []) as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            [$blueprintPattern, $pathPattern] = array_pad(explode(':', $pattern, 2), 2, null);

            if (! Str::is($blueprintPattern, $blueprint)) {
                continue;
            }

            if ($pathPattern === null || Str::is($pathPattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
