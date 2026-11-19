<x-layouts.app :title="__('Edit :name', ['name' => $method->name])">
    <x-ui.card class="max-w-xl">
        <form method="POST" action="{{ route('payment-methods.update', $method) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('payment-methods._fields', ['method' => $method])
            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit">{{ __('Save method') }}</x-ui.button>
                <x-ui.button :href="route('payment-methods.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
