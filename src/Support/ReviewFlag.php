<?php

namespace Kreatif\Translator\Support;

use Statamic\Contracts\Entries\Entry;

/**
 * Zentrale Helfer für das Review-Flag (Feldnamen aus der Config).
 */
final class ReviewFlag
{
    public static function enabled(): bool
    {
        return (bool) config('statamic-translator.review.enabled', true);
    }

    public static function field(): string
    {
        return (string) config('statamic-translator.review.flag_field', 'needs_review');
    }

    public static function timestampField(): string
    {
        return (string) config('statamic-translator.review.timestamp_field', 'machine_translated_at');
    }

    public static function sourceField(): string
    {
        return (string) config('statamic-translator.review.source_field', 'machine_translated_from');
    }

    public static function isSet(Entry $entry): bool
    {
        return (bool) $entry->get(self::field());
    }

    public static function clear(Entry $entry): void
    {
        $entry->remove(self::field());
    }

    public static function collectionExcluded(?string $collectionHandle): bool
    {
        if ($collectionHandle === null) {
            return false;
        }

        return in_array($collectionHandle, (array) config('statamic-translator.review.exclude_collections', []), true);
    }
}
