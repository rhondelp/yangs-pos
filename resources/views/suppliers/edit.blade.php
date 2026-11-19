<x-layouts.app :title="__('Edit :name', ['name' => $supplier->name])">
    <x-ui.card class="max-w-2xl">
        <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="grid gap-5 sm:grid-cols-2">
            @csrf
            @method('PUT')
            <x-ui.input name="name" :label="__('Name')" :value="$supplier->name" required maxlength="120" class="sm:col-span-2" />
            <x-ui.input name="contact_person" :label="__('Contact person')" :value="$supplier->contact_person" maxlength="120" />
            <x-ui.input name="phone" type="tel" :label="__('Phone')" :value="$supplier->phone" maxlength="40" />
            <x-ui.input name="email" type="email" :label="__('Email')" :value="$supplier->email" maxlength="120" class="sm:col-span-2" />
            <x-ui.textarea name="address" :label="__('Address')" :value="$supplier->address" rows="2" maxlength="300" class="sm:col-span-2" />
            <x-ui.textarea name="notes" :label="__('Notes')" :value="$supplier->notes" rows="2" maxlength="500" class="sm:col-span-2" />
            <div class="flex flex-wrap gap-3 sm:col-span-2">
                <x-ui.button type="submit">{{ __('Save supplier') }}</x-ui.button>
                <x-ui.button :href="route('suppliers.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
