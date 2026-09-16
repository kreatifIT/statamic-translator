<?php

namespace Kreatif\Translator\Actions;

use Kreatif\Translator\Support\ReviewFlag;
use Statamic\Actions\Action;
use Statamic\Contracts\Entries\Entry;

/**
 * Entry-Action „Übersetzung freigeben": Flag entfernen und veröffentlichen.
 */
class ApproveTranslation extends Action
{
    protected $confirm = true;

    public static function title()
    {
        return __('statamic-translator::messages.approve_action');
    }

    public function visibleTo($item)
    {
        return $item instanceof Entry && ReviewFlag::enabled() && ReviewFlag::isSet($item);
    }

    public function visibleToBulk($items)
    {
        return $items->every(fn ($item) => $this->visibleTo($item));
    }

    public function authorize($user, $item)
    {
        return $item instanceof Entry && $user->can('publish', $item);
    }

    public function buttonText()
    {
        return __('statamic-translator::messages.approve_button');
    }

    public function confirmationText()
    {
        return __('statamic-translator::messages.approve_confirm');
    }

    public function run($items, $values)
    {
        $count = 0;

        foreach ($items as $entry) {
            ReviewFlag::clear($entry);
            $entry->published(true);
            $entry->save();
            $count++;
        }

        return trans_choice('statamic-translator::messages.approved', $count, ['count' => $count]);
    }
}
