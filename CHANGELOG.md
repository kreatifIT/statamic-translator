# Changelog — kreatif/statamic-translator

Kreatif KI-Übersetzung: Kontext-Schicht (Glossar, Tonalität, Firmenwissen) und Review-Workflow für Magic Translator (Statamic 6)

Versionierung nach [semver](https://semver.org/lang/de/): Ein **Major** bedeutet, dass ein Projekt beim
Update etwas anpassen muss (Konfiguration, Feldnamen, Blueprints). **Minor** bringt neue Funktionen
ohne Anpassung, **Patch** nur Fehlerbehebungen.

Bei jeder Änderung hier eine Zeile ergänzen, **bevor** der Tag gesetzt wird.

## [Unveröffentlicht]

## [1.0.1] — 2026-10-15

- Erlaubt Magic Translator 0.4 (`^0.3.2 || ^0.4.0`). 0.4.0 übersetzt Gruppen-Felder mit und schickt
  Link-Felder nicht mehr an die KI (URLs und Eintragsverweise bleiben erhalten). Events, Prompts,
  `BlueprintExclusions` und Blink-Schlüssel, auf denen dieses Paket aufsetzt, sind unverändert.
  Projekte auf 0.3.2 laufen ohne Anpassung weiter.

## [1.0.0] — 2026-09-16

Erste eigenständige Veröffentlichung. Vorher lag der Code als Kopie in jedem Projekt.

Voraussetzungen: PHP ^8.3, Statamic ^6.0.
