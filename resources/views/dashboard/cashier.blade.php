<x-layouts.pos :title="__('Tables')">
    <div class="grid h-full gap-4 lg:grid-cols-[1fr_22rem]">
        <section aria-labelledby="tables-heading" class="flex min-h-0 flex-col">
            <h2 id="tables-heading" class="sr-only">{{ __('Tables') }}</h2>
            <x-ui.empty-state :title="__('No tables yet')" class="flex-1 justify-center">
                {{ __('Tables appear here once the owner adds them. Then tap a table to open a tab.') }}
            </x-ui.empty-state>
        </section>

        <aside class="space-y-4" aria-label="{{ __('Shift and tabs') }}">
            <x-ui.card :title="__('My shift')">
                <p class="text-base text-muted">{{ __('No shift open yet.') }}</p>
            </x-ui.card>
            <x-ui.card :title="__('Pending payments')">
                <p class="text-base text-muted">{{ __('Bills waiting for payment appear here.') }}</p>
            </x-ui.card>
            <x-ui.card :title="__('Recent transactions')">
                <p class="text-base text-muted">{{ __('Your paid bills from this shift appear here.') }}</p>
            </x-ui.card>
        </aside>
    </div>
</x-layouts.pos>
