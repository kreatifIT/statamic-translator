<ui-widget title="{{ __('statamic-translator::messages.widget_title') }}" icon="dictionary-language-book">
    <div class="px-4 py-3 flex flex-col gap-3 text-sm">
        @if ($rows->isEmpty())
            <p class="text-gray-500">{{ __('statamic-translator::messages.widget_empty') }}</p>
        @else
            <p class="text-gray-500">{{ trans_choice('statamic-translator::messages.widget_count', $total, ['count' => $total]) }}</p>
            <table class="w-full">
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="py-1 pr-2 whitespace-nowrap align-top">
                            <span class="inline-block rounded px-1.5 py-0.5 text-xs font-medium uppercase bg-gray-200 dark:bg-gray-700">{{ $row['site_handle'] }}</span>
                        </td>
                        <td class="py-1 pr-2 align-top">
                            <a href="{{ $row['edit_url'] }}" class="font-medium">{{ $row['title'] }}</a>
                            <div class="text-xs text-gray-500">{{ $row['collection'] }}@if ($row['translated_at'] !== '') · {{ $row['translated_at'] }}@endif</div>
                        </td>
                        <td class="py-1 whitespace-nowrap align-top text-right text-xs">
                            @if ($row['published'])
                                <span class="text-green-600">{{ __('statamic-translator::messages.widget_status_live') }}</span>
                            @else
                                <span class="text-amber-600">{{ __('statamic-translator::messages.widget_status_draft') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if ($total > $limit)
                <p class="text-xs text-gray-500">{{ __('statamic-translator::messages.widget_more', ['count' => $total - $limit]) }}</p>
            @endif
        @endif
    </div>
</ui-widget>
