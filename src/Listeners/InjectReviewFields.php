<?php

namespace Kreatif\Translator\Listeners;

use ElSchneider\MagicTranslator\Support\BlueprintExclusions;
use Illuminate\Support\Str;
use Kreatif\Translator\Support\ReviewFlag;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Facades\Site;

/**
 * Injiziert die Review-Felder in die Sidebar aller Entry-Blueprints (nur Multisite).
 * Rein additiv, kein Eingriff in die Blueprint-Dateien des Projekts.
 */
class InjectReviewFields
{
    public function handle(EntryBlueprintFound $event): void
    {
        if (! ReviewFlag::enabled() || ! config('statamic-translator.review.inject_fields', true)) {
            return;
        }

        if (Site::all()->count() <= 1) {
            return;
        }

        $collection = $event->entry?->collectionHandle()
            ?? Str::after((string) $event->blueprint->namespace(), 'collections.');

        if ($collection === '' || ReviewFlag::collectionExcluded($collection)) {
            return;
        }

        if (BlueprintExclusions::contains($collection, $event->blueprint->handle())) {
            return;
        }

        $event->blueprint->ensureFieldInTab(ReviewFlag::field(), [
            'type' => 'toggle',
            'display' => __('statamic-translator::messages.field_needs_review'),
            'instructions' => __('statamic-translator::messages.field_needs_review_instructions'),
            'localizable' => true,
            'listable' => true,
            'default' => false,
        ], 'sidebar');

        $event->blueprint->ensureFieldInTab(ReviewFlag::timestampField(), [
            'type' => 'text',
            'display' => __('statamic-translator::messages.field_machine_translated_at'),
            'visibility' => 'read_only',
            'localizable' => true,
            'listable' => 'hidden',
        ], 'sidebar');

        $event->blueprint->ensureFieldInTab(ReviewFlag::sourceField(), [
            'type' => 'text',
            'display' => __('statamic-translator::messages.field_machine_translated_from'),
            'visibility' => 'read_only',
            'localizable' => true,
            'listable' => 'hidden',
        ], 'sidebar');
    }
}
