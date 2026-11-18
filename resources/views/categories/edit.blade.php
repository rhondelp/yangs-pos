<x-layouts.app :title="__('Edit :name', ['name' => $category->name])">
    <x-ui.card class="max-w-xl">
        <form method="POST" action="{{ route('categories.update', $category) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <x-ui.input name="name" :label="__('Name')" :value="$category->name" required autofocus />
            <x-ui.input name="sort_order" type="number" min="0" inputmode="numeric" :label="__('Position')" :value="$category->sort_order" :hint="__('Lower comes first on the menu.')" />
            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit">{{ __('Save category') }}</x-ui.button>
                <x-ui.button :href="route('categories.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
