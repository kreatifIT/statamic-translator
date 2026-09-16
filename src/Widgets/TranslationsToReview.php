<?php

namespace Kreatif\Translator\Widgets;

use Illuminate\Support\Carbon;
use Kreatif\Translator\Support\ReviewFlag;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Widgets\Widget;
use Throwable;

/**
 * Dashboard-Widget „Übersetzungen zur Prüfung".
 * Konfiguration in config/statamic/cp.php: ['type' => 'translations_to_review', 'limit' => 10, 'width' => 50]
 */
class TranslationsToReview extends Widget
{
    public function html()
    {
        if (! ReviewFlag::enabled()) {
            return '';
        }

        $user = User::current();

        if ($user === null) {
            return '';
        }

        $limit = max(1, (int) $this->config('limit', 10));

        $entries = Entry::query()
            ->where(ReviewFlag::field(), true)
            ->orderBy(ReviewFlag::timestampField(), 'desc')
            ->get()
            ->filter(fn ($entry) => $user->can('edit', $entry));

        $total = $entries->count();

        $rows = $entries->take($limit)->map(fn ($entry) => [
            'title' => (string) ($entry->get('title') ?: $entry->slug()),
            'site' => $entry->site()->name(),
            'site_handle' => $entry->locale(),
            'collection' => (string) $entry->collection()?->title(),
            'edit_url' => $entry->editUrl(),
            'published' => (bool) $entry->published(),
            'translated_at' => $this->humanTime($entry->get(ReviewFlag::timestampField())),
        ])->values();

        return view('kreatif-translator::widgets.review', [
            'rows' => $rows,
            'total' => $total,
            'limit' => $limit,
        ]);
    }

    private function humanTime(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->diffForHumans();
        } catch (Throwable) {
            return '';
        }
    }
}
