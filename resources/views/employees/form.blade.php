@php
    $editing = $employee !== null;
    $statuses = ['active' => __('Active'), 'on_leave' => __('On leave'), 'resigned' => __('Resigned'), 'terminated' => __('Terminated')];
    $payTypes = ['daily' => __('Daily'), 'weekly' => __('Weekly'), 'monthly' => __('Monthly'), 'custom' => __('Custom')];
@endphp

<x-layouts.app :title="$editing ? __('Edit :name', ['name' => $employee->full_name]) : __('Add employee')">
    <div class="mb-4">
        <x-ui.button :href="$editing ? route('employees.show', $employee) : route('employees.index')" variant="ghost" icon="chevron-left">{{ $editing ? $employee->full_name : __('Employees') }}</x-ui.button>
    </div>

    <form method="POST" action="{{ $editing ? route('employees.update', $employee) : route('employees.store') }}" class="grid max-w-4xl gap-4"
          x-data="{ sending: false, status: @js(old('employment_status', $employee?->employment_status->value ?? 'active')), payType: @js(old('pay_type', 'daily')) }" x-on:submit="sending = true">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card :title="__('Details')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="full_name" :label="__('Full name')" :value="$employee?->full_name" maxlength="120" required autocomplete="off" />
                <x-ui.input name="position" :label="__('Position')" :value="$employee?->position" maxlength="60" required :hint="__('For example: Bartender, Server')" />
                <x-ui.input name="phone" type="tel" :label="__('Phone')" :value="$employee?->phone" maxlength="30" />
                <x-ui.input name="email" type="email" :label="__('Email')" :value="$employee?->email" maxlength="120" />
                <x-ui.input name="address" :label="__('Address')" :value="$employee?->address" maxlength="300" class="sm:col-span-2" />
                <x-ui.input name="emergency_contact_name" :label="__('Emergency contact')" :value="$employee?->emergency_contact_name" maxlength="120" />
                <x-ui.input name="emergency_contact_phone" type="tel" :label="__('Emergency contact phone')" :value="$employee?->emergency_contact_phone" maxlength="30" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Employment')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select name="employment_status" :label="__('Status')" :options="$statuses" :selected="$employee?->employment_status->value ?? 'active'" x-model="status" required />
                <x-ui.input name="date_hired" type="date" :label="__('Date hired')" :value="$employee?->date_hired?->toDateString()" required />
                <div x-show="status === 'resigned' || status === 'terminated'" x-cloak class="sm:col-span-2">
                    <x-ui.input name="date_separated" type="date" :label="__('Last day of work')" :value="$employee?->date_separated?->toDateString()"
                                :hint="__('Shifts after this day are cancelled.')" />
                </div>
                <x-ui.select name="user_id" :label="__('Login (optional)')" :options="$logins" :selected="$employee?->user_id" :placeholder="__('No login')"
                             :hint="__('Link the account this person signs in with.')" class="sm:col-span-2" />
            </div>
        </x-ui.card>

        @unless ($editing)
            <x-ui.card :title="__('Pay')">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.select name="pay_type" :label="__('Pay type')" :options="$payTypes" selected="daily" x-model="payType" required />
                    <x-ui.input name="rate" inputmode="decimal" :label="__('Rate (₱)')" required />
                    <div x-show="payType === 'custom'" x-cloak>
                        <x-ui.input name="custom_basis" :label="__('Paid for')" maxlength="80" :hint="__('For example: per event')" />
                    </div>
                </div>
                <p class="mt-3 text-sm text-muted">{{ __('Starts on the hire date. Later raises go on the employee page, with their own start date.') }}</p>
            </x-ui.card>
        @endunless

        <div>
            <x-ui.button type="submit" size="lg" x-bind:disabled="sending">{{ $editing ? __('Save') : __('Add employee') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
