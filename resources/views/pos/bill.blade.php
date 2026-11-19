@php
    $label = $bootstrap['session']['table'];
    $field = 'block min-h-14 w-full rounded-control border border-edge bg-raised px-3 text-right font-display text-2xl font-semibold text-fg tabular focus:border-accent focus:ring-accent';
    $key = 'min-h-12 rounded-control bg-raised font-display text-2xl font-semibold text-fg transition-colors duration-150 hover:bg-line active:bg-line';
    $dialog = 'm-auto w-[min(30rem,calc(100vw-2rem))] rounded-card border border-line bg-surface p-0 text-fg';
@endphp

<x-layouts.pos :title="__('Bill for table :label', ['label' => $label])">
    <div x-data="billScreen(@js($bootstrap))" class="flex flex-col gap-4 lg:grid lg:h-full lg:min-h-0 lg:grid-cols-[minmax(0,1fr)_27rem]">

        {{-- Payment panel: first in portrait, right column in landscape. --}}
        <section class="flex flex-col gap-3 rounded-card border border-line bg-surface p-4 lg:order-last lg:min-h-0 lg:overflow-y-auto" aria-labelledby="balance-label">
            <div class="flex items-end justify-between gap-3">
                <div>
                    <p id="balance-label" class="text-base text-muted" x-text="closed ? '{{ __('Paid in full') }}' : '{{ __('Left to pay') }}'"></p>
                    <p class="font-display text-[2.5rem] font-bold leading-none text-accent tabular" x-text="peso(closed ? session.bill.total : session.bill.balance)" aria-live="polite"></p>
                </div>
                <p class="text-right text-sm text-muted" x-show="session.bill.paid !== '0.00' && ! closed">
                    {{ __('Paid so far') }}<br><span class="font-display text-lg font-semibold text-fg tabular" x-text="peso(session.bill.paid)"></span>
                </p>
            </div>

            {{-- Taking payment --}}
            <template x-if="! closed && ! free">
                <form class="flex flex-col gap-3" x-on:submit.prevent="pay()">
                    <fieldset>
                        <legend class="sr-only">{{ __('Payment method') }}</legend>
                        <div class="grid grid-cols-3 gap-2">
                            <template x-for="m in methods" :key="m.id">
                                <button type="button" x-on:click="choose(m)" x-text="m.name" x-bind:aria-pressed="(methodId === m.id).toString()"
                                        class="min-h-14 rounded-control border px-2 text-lg font-semibold transition-colors duration-150"
                                        x-bind:class="methodId === m.id ? 'border-accent bg-accent text-on-accent' : 'border-edge bg-raised text-fg hover:bg-line'"></button>
                            </template>
                        </div>
                    </fieldset>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="pay-amount" class="mb-1 block text-sm font-medium text-muted">{{ __('Amount') }}</label>
                            <input id="pay-amount" type="text" inputmode="decimal" autocomplete="off" x-model="amount" x-on:focus="target = 'amount'"
                                   x-bind:placeholder="session.bill.balance" class="{{ $field }}" x-bind:class="target === 'amount' && 'border-accent'">
                        </div>
                        <div x-show="method?.is_cash">
                            <label for="pay-tendered" class="mb-1 block text-sm font-medium text-muted">{{ __('Cash received') }}</label>
                            <input id="pay-tendered" type="text" inputmode="decimal" autocomplete="off" x-model="tendered" x-on:focus="target = 'tendered'"
                                   x-bind:placeholder="amountText" class="{{ $field }}" x-bind:class="target === 'tendered' && 'border-accent'">
                        </div>
                        <div x-show="method && ! method.is_cash">
                            <label for="pay-reference" class="mb-1 block text-sm font-medium text-muted">
                                <span x-text="method?.requires_reference ? '{{ __('Reference no.') }}' : '{{ __('Reference (optional)') }}'"></span>
                            </label>
                            <input id="pay-reference" type="text" autocomplete="off" maxlength="60" x-model="reference" x-on:focus="target = 'reference'"
                                   class="block min-h-14 w-full rounded-control border border-edge bg-raised px-3 text-lg text-fg focus:border-accent focus:ring-accent">
                        </div>
                    </div>

                    <div x-show="method?.is_cash" class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Quick cash') }}">
                        <template x-for="c in quickCash" :key="c">
                            <button type="button" x-on:click="tendered = (c / 100).toFixed(2); target = 'tendered'" x-text="peso(c)"
                                    class="min-h-12 flex-1 rounded-full border border-edge px-3 font-display text-lg font-semibold text-fg tabular hover:bg-raised"></button>
                        </template>
                    </div>

                    <p x-show="method?.is_cash" class="flex items-baseline justify-between text-lg">
                        <span class="text-muted">{{ __('Change') }}</span>
                        <span class="font-display text-2xl font-bold tabular" x-bind:class="changeCents < 0 ? 'text-danger' : 'text-fg'"
                              x-text="changeCents < 0 ? '{{ __('Short') }} ' + peso(-changeCents) : peso(changeCents)"></span>
                    </p>

                    {{-- Keypad types into the highlighted field; the fields also take a keyboard. --}}
                    <div x-show="target !== 'reference'" class="grid grid-cols-3 gap-2" role="group" aria-label="{{ __('Keypad') }}">
                        <template x-for="k in ['1','2','3','4','5','6','7','8','9','.','0','⌫']" :key="k">
                            <button type="button" x-on:click="press(k)" x-text="k" class="{{ $key }}"
                                    x-bind:aria-label="k === '⌫' ? '{{ __('Delete') }}' : (k === '.' ? '{{ __('Decimal point') }}' : k)"></button>
                        </template>
                    </div>

                    <p x-cloak x-show="error" x-text="error" role="alert" class="rounded-control border border-danger/40 bg-danger/10 p-3 text-base text-fg"></p>

                    <x-ui.button type="submit" size="lg" class="min-h-16 w-full text-xl" data-bill="pay" x-bind:disabled="busy || ! method">
                        <span x-show="! busy" x-text="`{{ __('Take') }} ${peso(amountCents)} ${method?.name ?? ''}`"></span>
                        <span x-cloak x-show="busy">{{ __('Recording…') }}</span>
                    </x-ui.button>
                </form>
            </template>

            {{-- Everything discounted: nothing to pay --}}
            <template x-if="! closed && free">
                <div class="flex flex-col gap-3">
                    <p x-show="session.bill.item_count === 0" class="text-base text-muted">{{ __('This tab has no items yet. Add items, or cancel the tab from the floor.') }}</p>
                    <p x-show="session.bill.item_count > 0" class="text-base text-muted">{{ __('The discount covers the whole bill. Close it without a payment.') }}</p>
                    <p x-cloak x-show="error" x-text="error" role="alert" class="rounded-control border border-danger/40 bg-danger/10 p-3 text-base text-fg"></p>
                    <x-ui.button x-show="session.bill.item_count > 0" size="lg" class="min-h-16 w-full text-xl" x-on:click="settle()" x-bind:disabled="busy">{{ __('Close bill') }}</x-ui.button>
                </div>
            </template>

            {{-- Paid --}}
            <template x-if="closed">
                <div class="flex flex-col gap-4">
                    <div x-show="changeDue > 0" class="rounded-card border-l-8 border-l-success bg-success/15 p-4" role="status">
                        <p class="text-base text-fg">{{ __('Give change') }}</p>
                        <p class="font-display text-5xl font-bold leading-tight text-fg tabular" x-text="peso(changeDue)"></p>
                    </div>
                    <p class="text-base text-muted">
                        {{ __('Receipt') }} <span class="font-semibold text-fg" x-text="session.receipt_number"></span>.
                        {{ __('Table :label is free.', ['label' => $label]) }}
                    </p>
                    <template x-if="session.receipt_url">
                        <x-ui.button size="lg" icon="printer" class="w-full" x-bind:href="session.receipt_url" href="#">{{ __('Print receipt') }}</x-ui.button>
                    </template>
                    <x-ui.button :href="$bootstrap['urls']['floor']" variant="secondary" size="lg" icon="map" class="w-full">{{ __('Back to floor') }}</x-ui.button>
                </div>
            </template>
        </section>

        {{-- The bill --}}
        <section class="flex flex-col gap-3 rounded-card border border-line bg-surface p-4 lg:min-h-0" aria-label="{{ __('Bill') }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-display text-xl font-semibold text-fg">{{ __('Items') }} <span class="text-muted tabular" x-text="`(${session.bill.item_count})`"></span></h2>
                @isset($bootstrap['urls']['pos'])
                    <x-ui.button x-show="! closed" :href="$bootstrap['urls']['pos']" variant="ghost" icon="plus">{{ __('Add items') }}</x-ui.button>
                @endisset
            </div>

            <ul class="divide-y divide-line lg:min-h-0 lg:flex-1 lg:overflow-y-auto">
                <template x-for="item in session.items" :key="item.id">
                    <li class="flex justify-between gap-3 py-2 text-base">
                        <span class="text-fg" x-text="`${item.quantity} × ${item.name}`"></span>
                        <span class="tabular text-fg" x-text="peso(item.line_total)"></span>
                    </li>
                </template>
            </ul>

            <dl class="space-y-1 border-t border-line pt-3 text-base">
                <div class="flex justify-between"><dt class="text-muted">{{ __('Items total') }}</dt><dd class="tabular" x-text="peso(session.bill.gross)"></dd></div>
                <div class="flex justify-between" x-show="session.bill.discounts !== '0.00'"><dt class="text-muted">{{ __('Item discounts') }}</dt><dd class="tabular" x-text="'-' + peso(session.bill.discounts)"></dd></div>
                <div class="flex items-center justify-between gap-2" x-show="session.bill.bill_discount !== '0.00'">
                    <dt class="text-muted">
                        <span x-text="session.bill.bill_discount_type === 'percent' ? `{{ __('Discount') }} ${Number(session.bill.bill_discount_value)}%` : '{{ __('Discount') }}'"></span>
                        <span class="block text-sm" x-text="session.bill.bill_discount_reason"></span>
                    </dt>
                    <dd class="tabular" x-text="'-' + peso(session.bill.bill_discount)"></dd>
                </div>
                <div class="flex items-baseline justify-between pt-1"><dt class="font-semibold text-fg">{{ __('Total') }}</dt><dd class="font-display text-2xl font-bold text-fg tabular" x-text="peso(session.bill.total)"></dd></div>
            </dl>

            @isset($bootstrap['urls']['discount'])
                <div x-show="! closed && session.bill.paid === '0.00'" class="flex gap-2">
                    <x-ui.button variant="secondary" class="flex-1" x-on:click="openDiscount()" x-text="session.bill.bill_discount_type ? '{{ __('Change discount') }}' : '{{ __('Add discount') }}'">{{ __('Add discount') }}</x-ui.button>
                    <x-ui.button variant="ghost" x-show="session.bill.bill_discount_type" x-on:click="removeDiscount()" x-bind:disabled="busy">{{ __('Remove discount') }}</x-ui.button>
                </div>
            @endisset

            <div x-show="session.bill.payments.length" class="border-t border-line pt-3">
                <h3 class="mb-1 text-sm font-medium text-muted">{{ __('Payments') }}</h3>
                <ul class="space-y-1">
                    <template x-for="p in session.bill.payments" :key="p.id">
                        <li class="flex justify-between gap-3 text-base">
                            <span>
                                <span x-text="p.method"></span>
                                <span class="text-sm text-muted" x-show="p.reference_no" x-text="`#${p.reference_no}`"></span>
                                <span class="text-sm text-muted" x-show="p.change_given !== '0.00'" x-text="`{{ __('change') }} ${peso(p.change_given)}`"></span>
                            </span>
                            <span class="tabular" x-text="peso(p.amount)"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </section>

        @isset($bootstrap['urls']['discount'])
            <dialog x-ref="discount" aria-labelledby="discount-title" class="{{ $dialog }}">
                <form class="grid gap-4 p-5" x-on:submit.prevent="applyDiscount()">
                    <h2 id="discount-title" class="font-display text-xl font-semibold">{{ __('Discount for table :label', ['label' => $label]) }}</h2>
                    <fieldset class="grid grid-cols-2 gap-2">
                        <legend class="sr-only">{{ __('Discount type') }}</legend>
                        <button type="button" x-on:click="discount.type = 'percent'" x-bind:aria-pressed="(discount.type === 'percent').toString()"
                                class="min-h-touch rounded-control border text-base font-semibold" x-bind:class="discount.type === 'percent' ? 'border-accent bg-accent text-on-accent' : 'border-edge bg-raised text-fg'">{{ __('Percent') }}</button>
                        <button type="button" x-on:click="discount.type = 'amount'" x-bind:aria-pressed="(discount.type === 'amount').toString()"
                                class="min-h-touch rounded-control border text-base font-semibold" x-bind:class="discount.type === 'amount' ? 'border-accent bg-accent text-on-accent' : 'border-edge bg-raised text-fg'">{{ __('Peso amount') }}</button>
                    </fieldset>
                    <div>
                        <label for="discount-value" class="mb-1 block text-sm font-medium text-muted" x-text="discount.type === 'percent' ? '{{ __('Percent off') }}' : '{{ __('Pesos off') }}'"></label>
                        <input id="discount-value" type="text" inputmode="decimal" required autocomplete="off" x-model="discount.value" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="discount-reason" class="mb-1 block text-sm font-medium text-muted">{{ __('Reason') }}</label>
                        <input id="discount-reason" type="text" required maxlength="200" x-model="discount.reason"
                               class="block min-h-touch w-full rounded-control border border-edge bg-raised px-3 text-base text-fg focus:border-accent focus:ring-accent">
                    </div>
                    <p x-cloak x-show="discount.error" x-text="discount.error" role="alert" class="rounded-control border border-danger/40 bg-danger/10 p-3 text-base text-fg"></p>
                    <div class="grid grid-cols-2 gap-2">
                        <x-ui.button variant="secondary" x-on:click="$refs.discount.close()">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" x-bind:disabled="busy">{{ __('Apply discount') }}</x-ui.button>
                    </div>
                </form>
            </dialog>
        @endisset
    </div>
</x-layouts.pos>
