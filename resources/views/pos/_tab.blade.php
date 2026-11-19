{{-- What this tab has ordered so far, newest round first, with the server's running total. --}}
<div class="flex min-h-0 flex-1 flex-col gap-3">
    <div class="flex items-center justify-between gap-3 rounded-card bg-raised p-4">
        <div>
            <p class="text-sm text-muted">{{ __('Tab total') }}</p>
            <p class="font-display text-3xl font-bold text-accent tabular" x-text="peso(session.bill.total)"></p>
            <p class="text-sm text-muted" x-text="`${session.bill.item_count} {{ __('items') }}`"></p>
        </div>
        {{-- Cashiers go to the bill screen; staff who cannot take payment get a preview. --}}
        @if ($bootstrap['urls']['bill'])
            <x-ui.button :href="$bootstrap['urls']['bill']" size="lg" icon="receipt">{{ __('Bill') }}</x-ui.button>
        @else
            <x-ui.button variant="secondary" size="lg" icon="receipt" x-on:click="$refs.bill.showModal()">{{ __('Bill') }}</x-ui.button>
        @endif
    </div>

    <p x-show="session.orders.length === 0" class="rounded-card border border-dashed border-line p-6 text-center text-base text-muted">
        {{ __('No orders sent yet.') }}
    </p>

    <ol class="min-h-0 flex-1 space-y-3 overflow-y-auto">
        <template x-for="order in [...session.orders].reverse()" :key="order.id">
            <li class="rounded-card border border-line p-3">
                <p class="mb-1 flex items-baseline justify-between gap-2">
                    <span class="font-display text-lg font-semibold text-fg" x-text="clock(order.created_at)"></span>
                    <span class="text-sm text-muted" x-text="order.reference"></span>
                </p>
                <ul>
                    <template x-for="item in order.items" :key="item.id">
                        <li class="flex min-h-touch items-center justify-between gap-3 text-base" x-bind:class="item.status === 'cancelled' ? 'text-muted line-through' : 'text-fg'">
                            {{-- Net of voids, so the rows add up to the tab total. --}}
                            <span>
                                <span x-text="`${item.quantity - item.voided_quantity} × ${item.product_name}${item.variant_name ? ', ' + item.variant_name : ''}`"></span>
                                <span class="block text-sm text-muted no-underline" x-show="item.voided_quantity" x-text="`${item.voided_quantity} {{ __('voided') }}`"></span>
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="tabular" x-text="peso(item.status === 'cancelled' ? item.line_total : item.net_total)"></span>
                                @if ($bootstrap['urls']['cancelItem'])
                                    {{-- Before any payment only; after that, units are voided on the bill screen. --}}
                                    <button type="button" x-show="item.status === 'active' && session.bill.paid === '0.00'" x-on:click="openCancel(item)"
                                            class="inline-flex min-h-touch items-center rounded-control px-3 text-base font-semibold text-danger hover:bg-raised">
                                        {{ __('Cancel') }}<span class="sr-only" x-text="item.product_name"></span>
                                    </button>
                                @endif
                            </span>
                        </li>
                    </template>
                </ul>
            </li>
        </template>
    </ol>

    @if ($bootstrap['urls']['cancelSession'])
        {{-- Opened by mistake, or every item cancelled: free the table without a bill. --}}
        <x-ui.button variant="ghost" class="text-danger" x-show="session.bill.item_count === 0 && session.bill.paid === '0.00'" x-on:click="openCancelTab()">
            {{ __('Cancel tab') }}
        </x-ui.button>
    @endif
</div>
