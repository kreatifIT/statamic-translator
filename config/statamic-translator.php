<?php

/*
|--------------------------------------------------------------------------
| Kreatif Translator — Kontext-Schicht und Review-Workflow für Magic Translator
|--------------------------------------------------------------------------
|
| Magic Translator (el-schneider/statamic-magic-translator) bleibt eine
| unveränderte Composer-Abhängigkeit. Dieses Addon ergänzt nur über
| Config, eigene Prompt-Views und Events. Alle projektspezifischen Werte
| kommen aus .env bzw. aus den Addon-Settings im CP (Tools → Addons).
|
*/

return [

    // Global Set mit den Unternehmensinfos (Site-Name für den Prompt).
    'company_global' => [
        'handle' => 'company_details',
        'name_field' => 'name',
    ],

    // Wie viel Zusatzkontext in den Prompt fließt.
    'context' => [
        // Bereits übersetzte Geschwister-Titel (Quelle → Ziel) aus derselben Collection.
        'sibling_titles' => (int) env('KREATIF_TRANSLATOR_SIBLINGS', 12),
        // Auszug einer bestehenden Ziel-Übersetzung (Zeichen), wenn neu übersetzt wird.
        'existing_translation_chars' => 800,
        // Obergrenze für Glossar-Zeilen im Prompt.
        'glossary_max' => 150,
    ],

    // Halbautomatischer Workflow: Maschine schreibt nur Vorschläge, Mensch gibt frei.
    'review' => [
        'enabled' => (bool) env('KREATIF_TRANSLATOR_REVIEW', true),
        // Neue Lokalisierung als Entwurf anlegen (nicht sofort online).
        'unpublish_new' => true,
        // Bestehende (live) Lokalisierung bei Re-Übersetzung offline nehmen? Nein.
        'unpublish_existing' => false,
        // Sidebar-Felder "Prüfung nötig" / "Maschinell übersetzt am" injizieren.
        'inject_fields' => true,
        // Flag beim Veröffentlichen durch einen Redakteur automatisch entfernen.
        'clear_on_publish' => true,
        'flag_field' => 'needs_review',
        'timestamp_field' => 'machine_translated_at',
        'source_field' => 'machine_translated_from',
        // Collections ohne Review-Felder (z. B. rein technische Collections).
        'exclude_collections' => [],
    ],

    'slug' => [
        // Bei neuen Lokalisierungen den Slug aus dem übersetzten Titel bilden.
        'generate_for_new' => true,
    ],

    'audit' => [
        // Muster (Str::is), die der Audit-Command ignoriert: "<blueprint-label>" oder "<blueprint-label>:<feldpfad>".
        // Peak-SEO-Felder und rein technische Globals sind bewusst je Sprache pflegbar.
        'ignore' => [
            'collections/*:seo_*',
            'collections/*:og_image',
            'collections/*:sitemap_*',
            'collections/*:schema_jsonld',
            'globals/seo',
            'globals/browser_appearance',
            'globals/configuration',
            'globals/cookie_and_legal',
            'globals/redirects',
        ],
    ],

    'prompts' => [
        // Eigene Prompt-Views verwenden (statt der Magic-Translator-Defaults).
        'override_magic_translator' => true,
    ],

];
