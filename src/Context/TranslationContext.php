<?php

namespace Kreatif\Translator\Context;

/**
 * Request-/Job-gebundener Kontext für die Prompt-Views (scoped Binding).
 * Wird vom Listener CaptureTranslationContext befüllt.
 */
final class TranslationContext
{
    public bool $active = false;

    public string $siteName = '';

    public string $sourceSite = '';

    public string $targetSite = '';

    public string $collectionTitle = '';

    public string $blueprintTitle = '';

    public string $entryTitle = '';

    public ?string $entryUrl = null;

    public ?string $parentTitle = null;

    public bool $isNewLocalization = true;

    public string $companyDescription = '';

    public string $toneOfVoice = '';

    public string $targetAudience = '';

    public string $formality = '';

    public string $languageRules = '';

    public string $styleRules = '';

    /** @var array<int, array{term: string, translation: string, note: string}> */
    public array $glossary = [];

    /** @var array<int, string> */
    public array $doNotTranslate = [];

    /** @var array<int, array{source: string, target: string}> */
    public array $examples = [];

    /** @var array<int, array{source: string, target: string}> */
    public array $siblingTitles = [];

    /** @var array<string, string> */
    public array $existingTranslation = [];

    public function reset(): void
    {
        foreach ((new \ReflectionClass($this))->getProperties() as $property) {
            $name = $property->getName();
            $type = (string) $property->getType();

            $this->$name = match (true) {
                $name === 'isNewLocalization' => true,
                $type === 'bool' => false,
                $type === 'array' => [],
                str_starts_with($type, '?') => null,
                default => '',
            };
        }
    }

    public function hasSettings(): bool
    {
        return $this->companyDescription !== ''
            || $this->toneOfVoice !== ''
            || $this->targetAudience !== ''
            || $this->formality !== ''
            || $this->languageRules !== ''
            || $this->styleRules !== ''
            || $this->glossary !== []
            || $this->doNotTranslate !== []
            || $this->examples !== [];
    }
}
