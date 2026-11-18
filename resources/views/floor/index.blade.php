@php
    use App\Enums\TableStatus;

    $canChange = auth()->user()->can('tables.update-status');
    $canOpen = auth()->user()->can('pos.open-session');
    $canOrder = auth()->user()->can('pos.create-order');
    // Literal class strings so Tailwind sees them. The badge carries the text label; the rule is a second cue.
    $rule = fn (TableStatus $s) => match ($s) {
        TableStatus::Available => 'border-l-success',
        TableStatus::Occupied => 'border-l-info',
        TableStatus::AwaitingPayment => 'border-l-warning',
        TableStatus::Reserved => 'border-l-reserved',
        TableStatus::Cleaning => 'border-l-edge',
    };
    $chip = 'inline-flex min-h-touch items-center gap-2 rounded-full border px-4 text-base font-medium transition-colors duration-150';
@endphp

<x-layouts.pos :title="__('Floor')">
    <nav aria-label="{{ __('Filter tables by status') }}" class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('floor') }}" @if (! $current) aria-current="page" @endif
           @class([$chip, 'border-accent bg-accent text-on-accent' => ! $current, 'border-line text-fg hover:bg-raised' => $current])>
            {{ __('All') }} <span class="tabular">{{ array_sum($counts) }}</span>
        </a>
        @foreach (TableStatus::cases() as $status)
            <a href="{{ route('floor', ['status' => $status->value]) }}" @if ($current === $status) aria-current="page" @endif
               @class([$chip, 'border-accent bg-accent text-on-accent' => $current === $status, 'border-line text-fg hover:bg-raised' => $current !== $status])>
                {{ __($status->label()) }} <span class="tabular">{{ $counts[$status->value] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    @if ($tables->isEmpty())
        @if (array_sum($counts) === 0)
            <x-ui.empty-state :title="__('No tables yet')" icon="map">
                {{ __('Tables appear here once the owner adds them. Then tap a table to change its status or open a tab.') }}
                @can('tables.manage')
                    <x-slot:actions><x-ui.button :href="route('tables.create')" icon="plus">{{ __('Add a table') }}</x-ui.button></x-slot:actions>
                @endcan
            </x-ui.empty-state>
        @else
            <x-ui.empty-state :title="__('No tables are :status right now', ['status' => strtolower($current->label())])" icon="map">
                <x-slot:actions><x-ui.button :href="route('floor')" variant="secondary">{{ __('Show all tables') }}</x-ui.button></x-slot:actions>
            </x-ui.empty-state>
        @endif
    @else
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($tables as $table)
                @php($targets = $canChange ? $table->status->manualTargets() : [])
                @php($canOpenHere = $canOpen && in_array($table->status, [TableStatus::Available, TableStatus::Reserved], true))
                @php($tabUrl = $table->openSession && $canOrder ? route('pos.show', $table->openSession) : null)
                @php($hasDialog = ! $tabUrl && ($targets || $canOpenHere))
                @php($cardBody = 'flex min-h-32 w-full flex-col justify-between gap-3 rounded-tile border border-l-8 border-line bg-surface p-4 text-left '.$rule($table->status))
                <li>
                    @if ($tabUrl)
                        {{-- A table with a tab goes straight to its POS screen. --}}
                        <a href="{{ $tabUrl }}" class="{{ $cardBody }} transition-colors duration-150 hover:bg-raised">
                    @elseif ($hasDialog)
                        <button type="button" x-data x-on:click="document.getElementById('table-{{ $table->id }}').showModal()"
                                class="{{ $cardBody }} transition-colors duration-150 hover:bg-raised"
                                aria-haspopup="dialog">
                    @else
                        <div class="{{ $cardBody }}">
                    @endif
                            <span class="flex items-start justify-between gap-2">
                                <span class="font-display text-4xl font-bold leading-none text-fg">{{ $table->label }}</span>
                                @if ($table->capacity)
                                    <span class="text-sm text-muted">{{ trans_choice(':count seat|:count seats', $table->capacity) }}</span>
                                @endif
                            </span>
                            <x-ui.status-badge :status="$table->status" class="self-start" />
                    @if ($tabUrl)
                        </a>
                    @elseif ($hasDialog)
                        </button>
                    @else
                        </div>
                    @endif

                    @if ($hasDialog)
                        <x-ui.modal :id="'table-'.$table->id" :title="__('Table :label', ['label' => $table->label])">
                            @if ($canOpenHere)
                                <div x-data="openTab({ url: @js(route('api.v1.sessions.store', $table)), posUrl: @js(route('pos.show', ['session' => '__ID__'])) })" class="mb-5">
                                    <x-ui.button size="lg" class="w-full" x-on:click="open()" x-bind:disabled="busy">
                                        <span x-show="! busy">{{ __('Open tab') }}</span>
                                        <span x-cloak x-show="busy">{{ __('Opening…') }}</span>
                                    </x-ui.button>
                                    <p x-cloak x-show="error" x-text="error" role="alert" class="mt-3 text-sm font-medium text-danger"></p>
                                </div>
                            @endif
                            @if ($targets)
                                <p class="mb-4 text-base text-muted">{{ __('Now :status. Change it to:', ['status' => strtolower($table->status->label())]) }}</p>
                            @endif
                            <div class="grid gap-3">
                                @foreach ($targets as $to)
                                    <form method="POST" action="{{ route('tables.status', $table) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $to->value }}">
                                        <x-ui.button type="submit" variant="secondary" size="lg" class="w-full">{{ __('Mark :status', ['status' => strtolower($to->label())]) }}</x-ui.button>
                                    </form>
                                @endforeach
                            </div>
                        </x-ui.modal>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @error('status')
        <x-ui.alert variant="error" class="mt-4">{{ $message }}</x-ui.alert>
    @enderror
</x-layouts.pos>
