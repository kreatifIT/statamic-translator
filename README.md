# Kreatif Translator

KI-Übersetzung mit Kontextverstand und Review-Workflow für Statamic 6.
Basis ist das MIT-Addon [Magic Translator](https://github.com/el-schneider/statamic-magic-translator),
das **unverändert** als Composer-Abhängigkeit läuft und normal aktualisierbar bleibt. Dieses Addon
ergänzt nur über Config, eigene Prompt-Views und die Events `BeforeEntryTranslation` /
`AfterEntryTranslation`.

## Was das Addon ergänzt

| Baustein | Wirkung |
|---|---|
| **Addon-Settings** (CP: Tools → Addons → Kreatif Translator) | Unternehmensbeschreibung, Tonalität, Zielgruppe, Anrede je Sprache, Glossar, „nie übersetzen", Stilregeln, Musterübersetzungen |
| **Kontext im Prompt** | Zusätzlich automatisch: Site-Name (aus `company_details`), Collection/Eintrag, übergeordnete Seite, bereits übersetzte Geschwister-Titel (Quelle → Ziel), Auszug einer bestehenden Übersetzung |
| **Review-Workflow** | Neue Lokalisierungen entstehen als **Entwurf** mit Flag „Prüfung nötig"; bestehende Live-Seiten bleiben online und bekommen nur das Flag. Beim Veröffentlichen/Speichern durch einen Redakteur verschwindet das Flag. |
| **Action „Übersetzung freigeben"** | Im Listing (auch bulk): Flag entfernen + veröffentlichen |
| **Widget „Übersetzungen zur Prüfung"** | Dashboard-Liste aller offenen Übersetzungen mit Direktlink |
| **`php please kreatif:translator:audit`** | Findet Felder, die fälschlich übersetzt würden (URLs, Telefon, Codes) oder unnötig lokalisierbar sind |
| **Sprachdateien de/it** für Magic Translator | `php artisan vendor:publish --tag=statamic-translator-lang` |

## Setup

```bash
composer require kreatif/statamic-translator          # (Path-Repo: addons/kreatif/statamic-translator)
php artisan vendor:publish --tag=statamic-translator-lang
```

`.env`:

```dotenv
QUEUE_CONNECTION=database                  # Übersetzungen laufen als Queue-Jobs
MAGIC_TRANSLATOR_SERVICE=prism
MAGIC_TRANSLATOR_PROVIDER=openai
MAGIC_TRANSLATOR_MODEL=gpt-4.1-mini
OPENAI_API_KEY=sk-...
MAGIC_TRANSLATOR_QUEUE_CONNECTION=database
MAGIC_TRANSLATOR_QUEUE_NAME=translations
KREATIF_TRANSLATOR_REVIEW=true
```

Queue-Worker: lokal `./peaky.sh queue` (oder `queue-once`), auf Ploi ein Daemon
`php artisan queue:work database --queue=translations,default --sleep=3 --tries=3 --max-time=3600`.

Widget in `config/statamic/cp.php`:

```php
['type' => 'translations_to_review', 'limit' => 10, 'width' => 50],
```

Redakteure, die die Settings pflegen sollen, brauchen die Berechtigung **„Addons konfigurieren"**.

## Redaktions-Workflow

1. **Kontext pflegen** — einmalig unter Tools → Addons → Kreatif Translator: Firma, Tonalität, Anrede je Sprache, Glossar, Produktnamen unter „Nie übersetzen".
2. **Übersetzen** — im Eintrag in der Sidebar „Übersetzen" (Zielsprachen wählen) oder im Listing mehrere Einträge markieren → Aktion „Übersetzen".
3. **Prüfen** — das Dashboard-Widget listet alle Einträge mit „Prüfung nötig". Neue Übersetzungen sind Entwürfe (nicht online).
4. **Freigeben** — Eintrag veröffentlichen (Flag verschwindet automatisch) oder im Listing „Übersetzung freigeben".

Stale-Erkennung: Ändert sich die Quelle, zeigt Magic Translator in der Sidebar „Veraltet" — erneut übersetzen
(bestehende Live-Seite bleibt online, bekommt aber wieder das Prüf-Flag).

## Felder von der Übersetzung ausschließen

- `localizable: false` — Feld ist sprachunabhängig (Zahlen, Medien, Auswahlen, Relationen). Wird nie übersetzt.
- `translatable: false` — Feld ist zwar lokalisierbar, enthält aber keinen sprachlichen Inhalt (URL, E-Mail, Telefon, Tracking-Event). Wirkt in **jeder** Verschachtelungstiefe (Replicator-Sets, Grid).

`php please kreatif:translator:audit` listet Kandidaten (`--strict` für CI, `--json` für Tools).

## Konfiguration

`config/statamic-translator.php` (publizierbar mit `--tag=statamic-translator-config`): Review-Verhalten
(`unpublish_new`, `unpublish_existing`, `clear_on_publish`, `exclude_collections`), Kontextumfang
(`sibling_titles`, `existing_translation_chars`), Slug-Erzeugung, Prompt-Override.

Eigene Prompts: `prompts.override_magic_translator = false` setzen und in
`config/statamic/magic-translator.php` andere Views eintragen — oder die Kreatif-Views als Vorlage kopieren
(`resources/views/prompts/system.blade.php`). Der Kontext ist in jeder View über
`app(\Kreatif\Translator\Context\TranslationContext::class)` verfügbar.

## Datenmodell

Pro Lokalisierung: `needs_review: true`, `machine_translated_at` (ISO 8601), `machine_translated_from` (Site-Handle).
Magic Translator schreibt zusätzlich `magic_translator.last_translated_at` / `source_content_hash` (Stale-Erkennung).
