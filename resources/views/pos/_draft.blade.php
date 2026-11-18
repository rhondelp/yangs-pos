{{-- The order being built. Nothing is saved until "Send order". Used in the side panel and the portrait drawer. --}}
@php($round = 'inline-flex size-touch shrink-0 items-center justify-center rounded-control border border-edge bg-raised text-fg hover:bg-line disabled:opacity-40')
<div class="flex min-h-0 flex-1 flex-col gap-3">
    <p x-show="draft.length === 0" class="rounded-card border border-dashed border-line p-6 text-center text-base text-muted">
        {{ __('Tap products to add them to this order.') }}
    </p>

    <ul class="min-h-0 flex-1 space-y-2 overflow-y-auto" x-show="draft.length > 0">
        <template x-for="line in draft" :key="line.key">
            <li class="rounded-control border border-line bg-bg/40 p-3">
                <div class="flex items-start justify-between gap-3">
                    <p class="min-w-0">
                        <span class="block text-base font-medium text-fg" x-text="line.name"></span>
                        <span class="block text-sm text-muted" x-show="line.variant" x-text="line.variant"></span>
                    </p>
                    <span class="font-display text-lg font-semibold text-fg tabular" x-text="lineTotal(line)"></span>
                </div>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-1" role="group" x-bind:aria-label="`{{ __('Quantity of') }} ${line.name}`">
                        <button type="button" class="{{ $round }}" x-on:click="step(line, -1)" x-bind:disabled="line.quantity <= 1"
                                x-bind:aria-label="`{{ __('One less') }} ${line.name}`">
                            <x-ui.icon name="minus" />
                        </button>
                        <span class="w-12 text-center font-display text-2xl font-semibold text-fg tabular" x-text="line.quantity" aria-live="polite"></span>
                        <button type="button" class="{{ $round }}" x-on:click="step(line, 1)" x-bind:disabled="line.quantity >= 99"
                                x-bind:aria-label="`{{ __('One more') }} ${line.name}`">
                            <x-ui.icon name="plus" />
                        </button>
                    </div>
                    <button type="button" class="inline-flex size-touch items-center justify-center rounded-control text-muted hover:bg-raised hover:text-danger"
                            x-on:click="remove(line)" x-bind:aria-label="`{{ __('Remove') }} ${line.name}`">
                        <x-ui.icon name="trash" />
                    </button>
                </div>
            </li>
        </template>
    </ul>

    <p x-cloak x-show="error" x-text="error" role="alert" class="rounded-control border border-danger/40 bg-danger/10 p-3 text-base text-fg"></p>

    <div class="border-t border-line pt-3">
        <p class="mb-3 flex items-baseline justify-between">
            <span class="text-base text-muted" x-text="`${draftCount} {{ __('items') }}`"></span>
            <span class="font-display text-3xl font-bold text-fg tabular" x-text="peso(draftTotal)"></span>
        </p>
        <x-ui.button size="lg" class="w-full" x-on:click="send()" x-bind:disabled="sending || draft.length === 0">
            <span x-show="! sending">{{ __('Send order') }}</span>
            <span x-cloak x-show="sending">{{ __('Sending…') }}</span>
        </x-ui.button>
    </div>
</div>
