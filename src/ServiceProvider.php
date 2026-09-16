<?php

namespace Kreatif\Translator;

use Kreatif\Translator\Context\TranslationContext;
use Kreatif\Translator\Settings\SettingsBlueprint;
use Statamic\Providers\AddonServiceProvider;

/**
 * Kreatif Translator — Kontext-Schicht und Review-Workflow für Magic Translator.
 *
 * Listener, Widgets, Actions und Commands werden über die Ordner-Konvention
 * des AddonServiceProviders automatisch registriert (src/Listeners, src/Widgets, …).
 */
class ServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'kreatif-translator';

    public function register(): void
    {
        parent::register();

        // Ein Kontext-Objekt je Request/Job: BeforeEntryTranslation füllt es,
        // die Prompt-Views lesen es.
        $this->app->scoped(TranslationContext::class);
    }

    public function bootAddon(): void
    {
        // Explizit registrieren: $viewNamespace gibt es erst in neueren Statamic-6-Versionen.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'kreatif-translator');

        // Redaktionelle Einstellungen (Firma, Tonalität, Glossar) im CP unter Tools → Addons.
        // Direkt als Container-Binding (lazy, Site-abhängig) — registerSettingsBlueprint() akzeptiert
        // Closures erst ab Statamic 6.3x, das Binding funktioniert in allen 6er-Versionen.
        $this->app->scoped(
            "statamic.addons.{$this->getAddon()->slug()}.settings_blueprint",
            fn () => SettingsBlueprint::make(),
        );

        // Prompt-Views von Magic Translator auf unsere Views umbiegen — erst nach dem
        // Boot aller Provider, damit mergeConfigFrom() des Vendors bereits gelaufen ist.
        $this->app->booted(function () {
            if (! config('statamic-translator.prompts.override_magic_translator', true)) {
                return;
            }

            // Nur übernehmen, solange das Projekt keine eigenen Views eingetragen hat.
            if (config('statamic.magic-translator.prism.prompts.system') !== 'magic-translator::prompts.system') {
                return;
            }

            config([
                'statamic.magic-translator.prism.prompts.system' => 'kreatif-translator::prompts.system',
                'statamic.magic-translator.prism.prompts.user' => 'kreatif-translator::prompts.user',
            ]);
        });

        // Deutsche und italienische CP-Texte für Magic Translator (der Vendor liefert nur en).
        $this->publishes([
            __DIR__.'/../resources/lang-vendor/magic-translator' => lang_path('vendor/magic-translator'),
        ], 'statamic-translator-lang');
    }
}
