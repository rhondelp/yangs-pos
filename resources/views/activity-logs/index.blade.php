<x-layouts.app :title="__('Activity log')">
    <form method="GET" action="{{ route('activity-logs.index') }}" class="grid gap-4 rounded-card border border-line bg-surface p-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_auto_auto_auto] xl:items-end">
        <x-ui.select name="user_id" :label="__('User')" :placeholder="__('Everyone')" :selected="$filters['user_id'] ?? null"
                     :options="$users->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->username.')'])->all()" />
        <x-ui.select name="action" :label="__('Action')" :placeholder="__('All actions')" :selected="$filters['action'] ?? null"
                     :options="$actions->mapWithKeys(fn ($a) => [$a => $a])->all()" />
        <x-ui.input name="from" type="date" :label="__('From')" :value="$filters['from'] ?? null" />
        <x-ui.input name="to" type="date" :label="__('To')" :value="$filters['to'] ?? null" />
        <div class="flex gap-2">
            <x-ui.button type="submit">{{ __('Filter') }}</x-ui.button>
            <x-ui.button :href="route('activity-logs.index')" variant="ghost">{{ __('Reset') }}</x-ui.button>
        </div>
    </form>

    @if ($logs->isEmpty())
        <x-ui.empty-state :title="__('No activity matches these filters')">{{ __('Try a wider date range or reset the filters.') }}</x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('When') }}</th>
                    <th scope="col">{{ __('User') }}</th>
                    <th scope="col">{{ __('Action') }}</th>
                    <th scope="col">{{ __('Record') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('Details') }}</span></th>
                </tr>
            </x-slot:head>
            @foreach ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap text-muted tabular">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td class="text-fg">{{ $log->user_name ?? __('System') }}</td>
                    <td class="text-base text-fg">{{ $log->action }}</td>
                    <td class="text-muted">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                    <td class="text-right">
                        <x-ui.button :href="route('activity-logs.show', $log)" variant="ghost">
                            {{ __('View') }}<span class="sr-only"> {{ $log->action }}</span>
                        </x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    {{ $logs->links() }}
</x-layouts.app>
