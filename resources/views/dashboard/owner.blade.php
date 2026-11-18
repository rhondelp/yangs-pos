<x-layouts.app :title="__('Dashboard')">
    <section aria-labelledby="today-heading" class="space-y-3">
        <h2 id="today-heading" class="font-display text-lg font-semibold text-muted">{{ now()->format('l, F j') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($stats as $stat)
                <x-ui.stat-card :label="__($stat['label'])" :value="$stat['value']" :empty="__($stat['empty'])" />
            @endforeach
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card :title="__('Best-selling products')">
            <x-ui.empty-state :title="__('No sales yet')">{{ __('Your top sellers appear here once orders are paid.') }}</x-ui.empty-state>
        </x-ui.card>

        <x-ui.card :title="__('Low-stock products')">
            <x-ui.empty-state :title="__('Nothing to restock')">{{ __('Items running low appear here once inventory is tracked.') }}</x-ui.empty-state>
        </x-ui.card>

        <x-ui.card :title="__('Upcoming shifts')">
            <x-ui.empty-state :title="__('No shifts scheduled')">{{ __('The next 24 hours of shifts appear here once staff schedules exist.') }}</x-ui.empty-state>
        </x-ui.card>

        <x-ui.card :title="__('Recent activity')" :padded="$recentActivity->isEmpty()">
            @can('logs.view')
                <x-slot:actions>
                    <x-ui.button :href="route('activity-logs.index')" variant="ghost">{{ __('View all') }}</x-ui.button>
                </x-slot:actions>
            @endcan
            @if ($recentActivity->isEmpty())
                <x-ui.empty-state :title="__('No activity yet')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($recentActivity as $log)
                        <li>
                            <a href="{{ route('activity-logs.show', $log) }}" class="flex min-h-touch items-center justify-between gap-4 px-5 py-2 hover:bg-raised">
                                <span class="min-w-0">
                                    <span class="block truncate text-base text-fg">{{ $log->action }}</span>
                                    <span class="block text-sm text-muted">{{ $log->user_name ?? __('System') }}</span>
                                </span>
                                <time datetime="{{ $log->created_at->toIso8601String() }}" class="shrink-0 text-sm text-muted tabular">{{ $log->created_at->format('M j, H:i') }}</time>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
