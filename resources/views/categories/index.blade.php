<x-layouts.app :title="__('Categories')">
    @error('category')
        <x-ui.alert variant="error">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.card :title="__('New category')">
        <form method="POST" action="{{ route('categories.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <x-ui.input name="name" :label="__('Name')" required class="min-w-56 flex-1" />
            <x-ui.input name="sort_order" type="number" min="0" inputmode="numeric" :label="__('Position')" :hint="__('Lower comes first')" class="w-36" />
            <x-ui.button type="submit" icon="plus">{{ __('Add category') }}</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('Name') }}</th>
                <th scope="col" class="text-right">{{ __('Products') }}</th>
                <th scope="col" class="text-right">{{ __('Position') }}</th>
                <th scope="col">{{ __('Status') }}</th>
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </x-slot:head>
        @foreach ($categories as $category)
            <tr>
                <td class="text-base text-fg">{{ $category->name }}</td>
                <td class="text-right text-muted tabular">{{ $category->products_count }}</td>
                <td class="text-right text-muted tabular">{{ $category->sort_order }}</td>
                <td><x-ui.status-badge :status="$category->trashed() ? 'archived' : 'active'" /></td>
                <td>
                    <div class="flex justify-end gap-1">
                        @if ($category->trashed())
                            <form method="POST" action="{{ route('categories.restore', $category) }}">
                                @csrf
                                <x-ui.button type="submit" variant="ghost">{{ __('Restore') }}<span class="sr-only"> {{ $category->name }}</span></x-ui.button>
                            </form>
                        @else
                            <x-ui.button :href="route('categories.edit', $category)" variant="ghost">{{ __('Edit') }}<span class="sr-only"> {{ $category->name }}</span></x-ui.button>
                            <form method="POST" action="{{ route('categories.archive', $category) }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="ghost">{{ __('Archive') }}<span class="sr-only"> {{ $category->name }}</span></x-ui.button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layouts.app>
