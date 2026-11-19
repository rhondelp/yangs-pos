<x-layouts.app :title="__('Settings')">
    <form method="POST" action="{{ route('settings.update') }}" class="max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card :title="__('Business details')">
            <p class="mb-5 text-base text-muted">{{ __('Printed at the top of every receipt.') }}</p>
            <div class="space-y-5">
                <x-ui.input name="business_name" :label="__('Business name')" :value="$settings['business_name']" required maxlength="120" />
                <x-ui.textarea name="business_address" :label="__('Address (optional)')" :value="$settings['business_address']" rows="2" maxlength="300" />
                <x-ui.input name="business_tin" :label="__('TIN (optional)')" :value="$settings['business_tin']" maxlength="30" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Receipts')">
            <x-ui.textarea name="receipt_footer" :label="__('Footer message')" :value="$settings['receipt_footer']" rows="2" maxlength="300"
                           :hint="__('Printed at the bottom of every receipt.')" />
        </x-ui.card>

        <x-ui.card :title="__('After a bill is paid')">
            <input type="hidden" name="clean_table_after_payment" value="0">
            <label for="clean_table_after_payment" class="flex min-h-touch items-start gap-3">
                <input id="clean_table_after_payment" name="clean_table_after_payment" type="checkbox" value="1"
                       class="mt-0.5 size-6 rounded border-edge bg-raised text-accent focus:ring-accent" @checked(old('clean_table_after_payment', $settings['clean_table_after_payment']))>
                <span>
                    <span class="block text-base text-fg">{{ __('Mark the table as Cleaning') }}</span>
                    <span class="block text-sm text-muted">{{ __('Off: the table becomes Available as soon as the bill is paid.') }}</span>
                </span>
            </label>
        </x-ui.card>

        <x-ui.card :title="__('Voids, refunds and corrections')">
            <input type="hidden" name="require_approval_pin" value="0">
            <label for="require_approval_pin" class="flex min-h-touch items-start gap-3">
                <input id="require_approval_pin" name="require_approval_pin" type="checkbox" value="1"
                       class="mt-0.5 size-6 rounded border-edge bg-raised text-accent focus:ring-accent" @checked(old('require_approval_pin', $settings['require_approval_pin']))>
                <span>
                    <span class="block text-base text-fg">{{ __('Ask for a supervisor PIN') }}</span>
                    <span class="block text-sm text-muted">{{ __('Every void, refund and correction also needs the username and PIN of someone who can approve. Approvers set their PIN on their profile page.') }}</span>
                </span>
            </label>
        </x-ui.card>

        <x-ui.button type="submit" size="lg">{{ __('Save settings') }}</x-ui.button>
    </form>
</x-layouts.app>
