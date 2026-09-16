<?php

namespace Kreatif\Translator\Listeners;

use ElSchneider\MagicTranslator\Events\BeforeEntryTranslation;
use Illuminate\Support\Facades\Log;
use Kreatif\Translator\Context\ContextBuilder;
use Kreatif\Translator\Context\TranslationContext;
use Throwable;

/**
 * Füllt den Übersetzungskontext, bevor Magic Translator den Prompt rendert.
 */
class CaptureTranslationContext
{
    public function handle(BeforeEntryTranslation $event): void
    {
        try {
            app(ContextBuilder::class)->build($event->entry, $event->targetSite);
        } catch (Throwable $e) {
            app(TranslationContext::class)->reset();

            Log::warning('[kreatif/statamic-translator] Kontext konnte nicht gebaut werden: '.$e->getMessage(), [
                'entry' => $event->entry->id(),
                'target_site' => $event->targetSite,
            ]);
        }
    }
}
