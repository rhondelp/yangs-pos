@php
    $sheet = 'm-0 mt-auto max-h-[85dvh] w-full max-w-none rounded-t-tile border border-line bg-surface p-0 text-fg';
    $tabBtn = 'inline-flex min-h-touch shrink-0 items-center rounded-full border px-4 text-base font-medium transition-colors duration-150';
@endphp

<x-layouts.pos :title="__('Table :label', ['label' => $bootstrap['session']['table']['label']])">
    <div x-data="posScreen(@js($bootstrap))" class="flex h-full min-h-0 flex-col gap-4 pb-24 lg:grid lg:grid-cols-[minmax(0,1fr)_24rem] lg:pb-0">

        {{-- Menu --}}
        <section class="flex min-h-0 min-w-0 flex-col gap-3" aria-label="{{ __('Menu') }}">
            <div class="flex gap-2">
                <label for="pos-search" class="sr-only">{{ __('Search products') }}</label>
                <input id="pos-search" type="search" x-model="query" placeholder="{{ __('Search products') }}" autocomplete="off"
                       class="block min-h-touch min-w-0 flex-1 rounded-control border border-edge bg-raised px-3 text-base text-fg placeholder:text-muted focus:border-accent focus:ring-accent">
                <x-ui.button variant="secondary" icon="map" x-on:click="$refs.switcher.showModal()">{{ __('Tables') }}</x-ui.button>
            </div>

            <nav class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="{{ __('Categories') }}" x-show="query.trim() === ''">
                <template x-for="c in menu" :key="c.id">
                    <button type="button" x-on:click="category = c.id" x-text="c.name" x-bind:aria-pressed="(category === c.id).toString()"
                            class="{{ $tabBtn }}" x-bind:class="category === c.id ? 'border-accent bg-accent text-on-accent' : 'border-line text-fg hover:bg-raised'"></button>
                </template>
            </nav>

            <p x-cloak x-show="products.length === 0" class="rounded-card border border-dashed border-line p-6 text-center text-base text-muted">
                {{ __('No products match.') }}
            </p>

            <ul class="grid min-h-0 grid-cols-2 content-start gap-3 overflow-y-auto sm:grid-cols-3 xl:grid-cols-4">
                <template x-for="p in products" :key="p.id">
                    <li>
                        <button type="button" x-on:click="tap(p)" x-bind:disabled="! p.available" x-bind:aria-disabled="(! p.available).toString()"
                                class="flex min-h-28 w-full flex-col justify-between gap-2 rounded-tile border border-line bg-surface p-3 text-left transition-colors duration-150 hover:bg-raised disabled:cursor-not-allowed disabled:opacity-50">
                            <span class="line-clamp-2 text-base font-medium text-fg" x-text="p.name"></span>
                            <span class="flex items-end justify-between gap-2">
                                <span class="font-display text-xl font-semibold text-fg tabular" x-text="peso(p.price)"></span>
                                <span x-show="! p.available" class="rounded-full bg-raised px-2 py-0.5 text-sm text-muted">{{ __('Unavailable') }}</span>
                                <span x-show="p.available && p.variants.length" class="text-sm text-muted" x-text="`${p.variants.length + 1} {{ __('options') }}`"></span>
                            </span>
                        </button>
                    </li>
                </template>
            </ul>
        </section>

        {{-- Landscape: side panel --}}
        <aside class="hidden min-h-0 flex-col gap-3 rounded-card border border-line bg-surface p-3 lg:flex" aria-label="{{ __('Order and tab') }}">
            <div class="grid grid-cols-2 gap-2" role="tablist">
                <button type="button" role="tab" x-on:click="panel = 'draft'" x-bind:aria-selected="(panel === 'draft').toString()"
                        class="min-h-touch rounded-control text-base font-semibold" x-bind:class="panel === 'draft' ? 'bg-accent text-on-accent' : 'bg-raised text-fg'">
                    {{ __('New order') }} <span class="tabular" x-text="`(${draftCount})`"></span>
                </button>
                <button type="button" role="tab" x-on:click="panel = 'tab'; refresh()" x-bind:aria-selected="(panel === 'tab').toString()"
                        class="min-h-touch rounded-control text-base font-semibold" x-bind:class="panel === 'tab' ? 'bg-accent text-on-accent' : 'bg-raised text-fg'">
                    {{ __('Tab') }} <span class="tabular" x-text="peso(session.bill.total)"></span>
                </button>
            </div>
            <div x-show="panel === 'draft'" class="flex min-h-0 flex-1 flex-col">@include('pos._draft')</div>
            <div x-cloak x-show="panel === 'tab'" class="flex min-h-0 flex-1 flex-col">@include('pos._tab')</div>
        </aside>

        {{-- Portrait: bottom bar and drawers --}}
        <div class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-2 gap-2 border-t border-line bg-surface p-3 lg:hidden">
            <x-ui.button variant="secondary" size="lg" data-pos="open-tab" x-on:click="refresh(); $refs.tabSheet.showModal()">
                {{ __('Tab') }} <span class="tabular" x-text="peso(session.bill.total)"></span>
            </x-ui.button>
            <x-ui.button size="lg" data-pos="open-order" x-on:click="$refs.draftSheet.showModal()">
                {{ __('Order') }} <span class="tabular" x-text="`(${draftCount})`"></span> <span class="tabular" x-text="peso(draftTotal)"></span>
            </x-ui.button>
        </div>

        <dialog x-ref="draftSheet" class="{{ $sheet }}" aria-label="{{ __('New order') }}">
            <div class="flex min-h-touch items-center justify-between border-b border-line py-2 pl-4 pr-2">
                <h2 class="font-display text-xl font-semibold">{{ __('New order') }}</h2>
                <form method="dialog"><button type="submit" class="inline-flex size-touch items-center justify-center rounded-control text-muted hover:bg-raised" aria-label="{{ __('Close') }}"><x-ui.icon name="close" /></button></form>
            </div>
            <div class="flex max-h-[calc(85dvh-4rem)] flex-col p-4">@include('pos._draft')</div>
        </dialog>

        <dialog x-ref="tabSheet" class="{{ $sheet }}" aria-label="{{ __('Tab') }}">
            <div class="flex min-h-touch items-center justify-between border-b border-line py-2 pl-4 pr-2">
                <h2 class="font-display text-xl font-semibold">{{ __('Tab') }}</h2>
                <form method="dialog"><button type="submit" class="inline-flex size-touch items-center justify-center rounded-control text-muted hover:bg-raised" aria-label="{{ __('Close') }}"><x-ui.icon name="close" /></button></form>
            </div>
            <div class="flex max-h-[calc(85dvh-4rem)] flex-col p-4">@include('pos._tab')</div>
        </dialog>

        {{-- Option picker --}}
        <dialog x-ref="variants" aria-labelledby="variants-title" class="m-auto w-[min(28rem,calc(100vw-2rem))] rounded-card border border-line bg-surface p-0 text-fg">
            <div class="flex min-h-touch items-center justify-between border-b border-line py-2 pl-5 pr-2">
                <h2 id="variants-title" class="font-display text-xl font-semibold" x-text="picking?.name"></h2>
                <form method="dialog"><button type="submit" class="inline-flex size-touch items-center justify-center rounded-control text-muted hover:bg-raised" aria-label="{{ __('Close') }}"><x-ui.icon name="close" /></button></form>
            </div>
            <div class="grid gap-3 p-5">
                <template x-if="picking">
                    <div class="grid gap-3">
                        <button type="button" x-on:click="pick(null)" class="flex min-h-14 items-center justify-between rounded-control border border-edge bg-raised px-4 text-left text-lg hover:bg-line">
                            <span>{{ __('Regular') }}</span><span class="font-display font-semibold tabular" x-text="peso(picking.price)"></span>
                        </button>
                        <template x-for="v in picking.variants" :key="v.id">
                            <button type="button" x-on:click="pick(v)" class="flex min-h-14 items-center justify-between rounded-control border border-edge bg-raised px-4 text-left text-lg hover:bg-line">
                                <span x-text="v.name"></span><span class="font-display font-semibold tabular" x-text="peso(v.price)"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </dialog>

        {{-- Bill preview for staff without payments.record; cashiers use the bill screen. --}}
        <dialog x-ref="bill" aria-labelledby="bill-title" class="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-card border border-line bg-surface p-0 text-fg">
            <div class="flex min-h-touch items-center justify-between border-b border-line py-2 pl-5 pr-2">
                <h2 id="bill-title" class="font-display text-xl font-semibold">{{ __('Bill for table :label', ['label' => $bootstrap['session']['table']['label']]) }}</h2>
                <form method="dialog"><button type="submit" class="inline-flex size-touch items-center justify-center rounded-control text-muted hover:bg-raised" aria-label="{{ __('Close') }}"><x-ui.icon name="close" /></button></form>
            </div>
            <div class="max-h-[70dvh] overflow-y-auto p-5">
                <ul class="divide-y divide-line">
                    <template x-for="item in activeItems" :key="item.id">
                        <li class="flex justify-between gap-3 py-2 text-base">
                            <span x-text="`${item.quantity - item.voided_quantity} × ${item.product_name}${item.variant_name ? ', ' + item.variant_name : ''}`"></span>
                            <span class="tabular" x-text="peso(item.net_total)"></span>
                        </li>
                    </template>
                </ul>
                <dl class="mt-4 space-y-1 border-t border-line pt-4 text-base">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Subtotal') }}</dt><dd class="tabular" x-text="peso(session.bill.gross)"></dd></div>
                    <div class="flex justify-between" x-show="session.bill.discounts !== '0.00'"><dt class="text-muted">{{ __('Discounts') }}</dt><dd class="tabular" x-text="'-' + peso(session.bill.discounts)"></dd></div>
                    <div class="flex items-baseline justify-between pt-2"><dt class="font-semibold">{{ __('Total') }}</dt><dd class="font-display text-3xl font-bold text-accent tabular" x-text="peso(session.bill.total)"></dd></div>
                </dl>
                <p class="mt-4 text-sm text-muted">{{ __('Ask the cashier to take payment.') }}</p>
            </div>
        </dialog>

        {{-- Quick table switcher --}}
        <dialog x-ref="switcher" aria-labelledby="switcher-title" class="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-card border border-line bg-surface p-0 text-fg">
            <div class="flex min-h-touch items-center justify-between border-b border-line py-2 pl-5 pr-2">
                <h2 id="switcher-title" class="font-display text-xl font-semibold">{{ __('Open tabs') }}</h2>
                <form method="dialog"><button type="submit" class="inline-flex size-touch items-center justify-center rounded-control text-muted hover:bg-raised" aria-label="{{ __('Close') }}"><x-ui.icon name="close" /></button></form>
            </div>
            <div class="grid grid-cols-3 gap-3 p-5 sm:grid-cols-4">
                <template x-for="t in tabs" :key="t.id">
                    <a x-bind:href="t.url" x-bind:aria-current="t.current ? 'page' : null"
                       class="flex min-h-20 items-center justify-center rounded-tile border font-display text-2xl font-bold"
                       x-bind:class="t.current ? 'border-accent bg-accent text-on-accent' : 'border-line bg-raised text-fg hover:bg-line'" x-text="t.label"></a>
                </template>
            </div>
            <div class="border-t border-line p-5">
                <x-ui.button :href="route('floor')" variant="secondary" size="lg" icon="map" class="w-full">{{ __('Go to the floor') }}</x-ui.button>
            </div>
        </dialog>

        @if ($bootstrap['urls']['cancelItem'] || $bootstrap['urls']['cancelSession'])
            <dialog x-ref="cancel" aria-labelledby="cancel-title" class="m-auto w-[min(30rem,calc(100vw-2rem))] rounded-card border border-line bg-surface p-0 text-fg">
                <form class="grid gap-4 p-5" x-on:submit.prevent="submitCancel()">
                    <h2 id="cancel-title" class="font-display text-xl font-semibold"
                        x-text="cancelling.item ? `{{ __('Cancel') }} ${cancelling.item.product_name}` : '{{ __('Cancel this tab') }}'"></h2>
                    <p class="text-base text-muted" x-text="cancelling.item ? '{{ __('The item comes off the tab. Nothing was paid yet.') }}' : '{{ __('The tab closes without a bill and the table is free again.') }}'"></p>
                    <div x-show="cancelling.item && cancelling.item.quantity > 1">
                        <label for="cancel-quantity" class="mb-1 block text-sm font-medium text-muted">{{ __('How many') }}</label>
                        <div class="flex items-center gap-2">
                            <button type="button" x-on:click="cancelling.quantity = Math.max(1, cancelling.quantity - 1)" class="inline-flex size-14 shrink-0 items-center justify-center rounded-control border border-edge bg-raised text-fg" aria-label="{{ __('One less') }}"><x-ui.icon name="minus" /></button>
                            <input id="cancel-quantity" type="number" min="1" x-bind:max="cancelling.item?.quantity" x-model.number="cancelling.quantity"
                                   class="block min-h-14 w-24 rounded-control border border-edge bg-raised px-3 text-center font-display text-2xl font-semibold text-fg tabular focus:border-accent focus:ring-accent">
                            <button type="button" x-on:click="cancelling.quantity = Math.min(cancelling.item?.quantity ?? 1, cancelling.quantity + 1)" class="inline-flex size-14 shrink-0 items-center justify-center rounded-control border border-edge bg-raised text-fg" aria-label="{{ __('One more') }}"><x-ui.icon name="plus" /></button>
                            <span class="text-base text-muted" x-text="`{{ __('of') }} ${cancelling.item?.quantity ?? ''}`"></span>
                        </div>
                    </div>
                    <div>
                        <label for="cancel-reason" class="mb-1 block text-sm font-medium text-muted">{{ __('Reason') }}</label>
                        <input id="cancel-reason" type="text" required maxlength="200" x-model="cancelling.reason"
                               class="block min-h-touch w-full rounded-control border border-edge bg-raised px-3 text-base text-fg focus:border-accent focus:ring-accent">
                    </div>
                    <p x-cloak x-show="cancelling.error" x-text="cancelling.error" role="alert" class="rounded-control border border-danger/40 bg-danger/10 p-3 text-base text-fg"></p>
                    <div class="grid grid-cols-2 gap-2">
                        <x-ui.button variant="secondary" x-on:click="$refs.cancel.close()">{{ __('Keep it') }}</x-ui.button>
                        <x-ui.button type="submit" variant="danger" x-bind:disabled="sending">{{ __('Cancel') }}</x-ui.button>
                    </div>
                </form>
            </dialog>
        @endif

        {{-- Toast --}}
        {{-- Bottom, above the portrait bar, and click-through so it never covers a control. --}}
        <p x-cloak x-show="toast" x-text="toast" role="status" aria-live="polite"
           class="pointer-events-none fixed bottom-24 left-1/2 z-30 -translate-x-1/2 whitespace-nowrap rounded-full bg-fg px-5 py-3 text-base font-medium text-bg shadow-lg lg:bottom-6"></p>
    </div>
</x-layouts.pos>
