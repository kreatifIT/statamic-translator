<?php

namespace Kreatif\Translator\Settings;

use Statamic\Facades\Site;

/**
 * Blueprint der Addon-Settings (CP: Tools → Addons → Kreatif Translator).
 *
 * Wird zur Laufzeit gebaut, damit Glossar-Spalten und Sprach-Optionen den
 * konfigurierten Sites folgen. Gespeichert wird in resources/addons/statamic-translator.yaml.
 */
final class SettingsBlueprint
{
    public static function make(): array
    {
        $sites = Site::all();
        $default = Site::default();
        $targets = $sites->reject(fn ($site) => $site->handle() === $default->handle());
        $siteOptions = $sites->mapWithKeys(fn ($site) => [$site->handle() => $site->name()])->all();
        $targetOptions = $targets->mapWithKeys(fn ($site) => [$site->handle() => $site->name()])->all();

        $t = fn (string $key, array $replace = []) => (string) __('statamic-translator::messages.'.$key, $replace);

        $glossaryFields = [
            self::field('term', [
                'type' => 'text',
                'display' => $t('settings.glossary_term', ['site' => $default->name()]),
                'width' => $targets->count() > 1 ? 25 : 33,
            ]),
        ];

        foreach ($targets as $site) {
            $glossaryFields[] = self::field('translation_'.$site->handle(), [
                'type' => 'text',
                'display' => $site->name(),
                'width' => $targets->count() > 1 ? 25 : 33,
            ]);
        }

        $glossaryFields[] = self::field('note', [
            'type' => 'text',
            'display' => $t('settings.glossary_note'),
            'width' => $targets->count() > 1 ? 25 : 33,
        ]);

        return [
            'tabs' => [
                'company' => [
                    'display' => $t('settings.tab_company'),
                    'sections' => [[
                        'display' => $t('settings.tab_company'),
                        'instructions' => $t('settings.tab_company_instructions'),
                        'fields' => [
                            self::field('company_description', [
                                'type' => 'textarea',
                                'display' => $t('settings.company_description'),
                                'instructions' => $t('settings.company_description_instructions'),
                                'character_limit' => 2000,
                            ]),
                            self::field('tone_of_voice', [
                                'type' => 'textarea',
                                'display' => $t('settings.tone_of_voice'),
                                'instructions' => $t('settings.tone_of_voice_instructions'),
                                'character_limit' => 1000,
                                'width' => 50,
                            ]),
                            self::field('target_audience', [
                                'type' => 'textarea',
                                'display' => $t('settings.target_audience'),
                                'instructions' => $t('settings.target_audience_instructions'),
                                'character_limit' => 1000,
                                'width' => 50,
                            ]),
                        ],
                    ]],
                ],
                'languages' => [
                    'display' => $t('settings.tab_languages'),
                    'sections' => [[
                        'display' => $t('settings.tab_languages'),
                        'instructions' => $t('settings.tab_languages_instructions'),
                        'fields' => [
                            self::field('language_rules', [
                                'type' => 'grid',
                                'display' => $t('settings.language_rules'),
                                'mode' => 'stacked',
                                'add_row' => $t('settings.language_rules_add'),
                                'fields' => [
                                    self::field('site', [
                                        'type' => 'select',
                                        'display' => $t('settings.site'),
                                        'options' => $siteOptions,
                                        'width' => 33,
                                        'validate' => ['required'],
                                    ]),
                                    self::field('formality', [
                                        'type' => 'select',
                                        'display' => $t('settings.formality'),
                                        'options' => [
                                            'formal' => $t('settings.formality_formal'),
                                            'informal' => $t('settings.formality_informal'),
                                            'neutral' => $t('settings.formality_neutral'),
                                        ],
                                        'default' => 'formal',
                                        'width' => 33,
                                    ]),
                                    self::field('rules', [
                                        'type' => 'textarea',
                                        'display' => $t('settings.language_rules_text'),
                                        'instructions' => $t('settings.language_rules_text_instructions'),
                                        'character_limit' => 1500,
                                    ]),
                                ],
                            ]),
                        ],
                    ]],
                ],
                'glossary' => [
                    'display' => $t('settings.tab_glossary'),
                    'sections' => [[
                        'display' => $t('settings.tab_glossary'),
                        'instructions' => $t('settings.tab_glossary_instructions'),
                        'fields' => [
                            self::field('glossary', [
                                'type' => 'grid',
                                'display' => $t('settings.glossary'),
                                'mode' => 'table',
                                'add_row' => $t('settings.glossary_add'),
                                'fields' => $glossaryFields,
                            ]),
                            self::field('do_not_translate', [
                                'type' => 'list',
                                'display' => $t('settings.do_not_translate'),
                                'instructions' => $t('settings.do_not_translate_instructions'),
                            ]),
                        ],
                    ]],
                ],
                'rules' => [
                    'display' => $t('settings.tab_rules'),
                    'sections' => [[
                        'display' => $t('settings.tab_rules'),
                        'fields' => [
                            self::field('style_rules', [
                                'type' => 'textarea',
                                'display' => $t('settings.style_rules'),
                                'instructions' => $t('settings.style_rules_instructions'),
                                'character_limit' => 2000,
                            ]),
                            self::field('examples', [
                                'type' => 'grid',
                                'display' => $t('settings.examples'),
                                'instructions' => $t('settings.examples_instructions'),
                                'mode' => 'stacked',
                                'add_row' => $t('settings.examples_add'),
                                'fields' => [
                                    self::field('target_site', [
                                        'type' => 'select',
                                        'display' => $t('settings.site'),
                                        'options' => $targetOptions,
                                        'width' => 33,
                                        'validate' => ['required'],
                                    ]),
                                    self::field('source', [
                                        'type' => 'textarea',
                                        'display' => $t('settings.example_source', ['site' => $default->name()]),
                                        'width' => 50,
                                    ]),
                                    self::field('target', [
                                        'type' => 'textarea',
                                        'display' => $t('settings.example_target'),
                                        'width' => 50,
                                    ]),
                                ],
                            ]),
                        ],
                    ]],
                ],
            ],
        ];
    }

    private static function field(string $handle, array $config): array
    {
        return ['handle' => $handle, 'field' => $config];
    }
}
