<?php

namespace Kreatif\Translator\Listeners;

use Kreatif\Translator\Support\ReviewFlag;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Blink;

/**
 * Speichert ein Redakteur eine veröffentlichte Lokalisierung, gilt die
 * Übersetzung als geprüft — das Flag wird entfernt. Der Speichervorgang von
 * Magic Translator selbst (Blink-Marker) wird übersprungen.
 */
class ClearReviewFlagOnPublish
{
    public function handle(EntrySaving $event): void
    {
        if (! ReviewFlag::enabled() || ! config('statamic-translator.review.clear_on_publish', true)) {
            return;
        }

        $entry = $event->entry;

        if (Blink::has("magic-translator:translating:{$entry->id()}")) {
            return;
        }

        if (! ReviewFlag::isSet($entry) || ! $entry->published()) {
            return;
        }

        ReviewFlag::clear($entry);
    }
}
