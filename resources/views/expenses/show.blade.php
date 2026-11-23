@php
    use App\Support\Money;
    $isImage = $expense->attachment_path && preg_match('/\.(jpe?g|png|webp)$/i', $expense->attachment_path);
@endphp

<x-layouts.app :title="$expense->description">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-ui.button :href="route('expenses.index')" variant="ghost" icon="chevron-left">{{ __('Expenses') }}</x-ui.button>
        @if ($expense->isVoided())
            <x-ui.status-badge status="voided" />
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card :title="__('Details')">
            <dl class="grid gap-3 text-base sm:grid-cols-2">
                <div><dt class="text-sm text-muted">{{ __('Amount') }}</dt><dd @class(['font-display text-3xl font-semibold tabular', 'line-through text-muted' => $expense->isVoided()])>{{ Money::format($expense->amount) }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('Date') }}</dt><dd>{{ $expense->expense_date->format('M j, Y') }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('Category') }}</dt><dd>{{ $expense->category->name }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('Recorded by') }}</dt><dd>{{ $expense->recordedBy?->name ?? '—' }}, {{ $expense->created_at->timezone(config('app.timezone'))->format('M j, g:i A') }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm text-muted">{{ __('Description') }}</dt><dd>{{ $expense->description }}</dd></div>
            </dl>

            @if ($expense->isVoided())
                <x-ui.alert variant="warning" :title="__('Voided')" class="mt-4">
                    {{ __('By :name on :date: :reason', ['name' => $expense->voidedBy?->name ?? '—', 'date' => $expense->voided_at->timezone(config('app.timezone'))->format('M j, g:i A'), 'reason' => $expense->void_reason]) }}
                </x-ui.alert>
                @can('expenses.manage')
                    <x-ui.button :href="route('expenses.create', ['from' => $expense->id])" variant="secondary" icon="plus" class="mt-4">{{ __('Record again') }}</x-ui.button>
                @endcan
            @else
                @can('expenses.manage')
                    <x-ui.button variant="danger" class="mt-4" x-data x-on:click="document.getElementById('void-expense').showModal()">{{ __('Void expense') }}</x-ui.button>
                @endcan
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Receipt')">
            @if ($expense->attachment_path === null)
                <p class="text-base text-muted">{{ __('No receipt attached.') }}</p>
            @elseif ($isImage)
                <a href="{{ route('expenses.attachment', $expense) }}" target="_blank" rel="noopener" class="block">
                    <img src="{{ route('expenses.attachment', $expense) }}" alt="{{ __('Receipt for :description', ['description' => $expense->description]) }}" class="max-h-[28rem] w-full rounded-control object-contain">
                </a>
            @else
                <x-ui.button :href="route('expenses.attachment', $expense)" variant="secondary" icon="receipt" target="_blank" rel="noopener">{{ __('Open :name', ['name' => $expense->attachment_name]) }}</x-ui.button>
            @endif
        </x-ui.card>
    </div>

    @if (! $expense->isVoided())
        @can('expenses.manage')
            <x-ui.modal id="void-expense" :title="__('Void this expense')">
                <form method="POST" action="{{ route('expenses.void', $expense) }}" class="space-y-4" x-data="{ sending: false }" x-on:submit="sending = true">
                    @csrf
                    <p class="text-base text-muted">{{ __('It stays on record, marked voided, and no longer counts in totals. Record it again if the details were wrong.') }}</p>
                    <x-ui.input id="void-reason" name="reason" maxlength="300" :label="__('Reason')" required />
                    <div class="flex justify-end">
                        <x-ui.button type="submit" variant="danger" x-bind:disabled="sending">{{ __('Void expense') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
            @error('reason')
                <div x-data x-init="document.getElementById('void-expense').showModal()"></div>
            @enderror
        @endcan
    @endif
</x-layouts.app>
