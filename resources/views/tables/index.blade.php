<x-layouts.app :title="__('Tables')">
    @error('table')
        <x-ui.alert variant="error">{{ $message }}</x-ui.alert>
    @enderror

    <div class="flex flex-wrap justify-end gap-3">
        <x-ui.button :href="route('floor')" variant="secondary" icon="map">{{ __('Open the floor') }}</x-ui.button>
        <x-ui.button :href="route('tables.create')" icon="plus">{{ __('New table') }}</x-ui.button>
    </div>

    @if ($tables->isEmpty())
        <x-ui.empty-state :title="__('No tables yet')" icon="squares">
            {{ __('Add each table, booth or bar spot customers can sit at. Cashiers open tabs on them from the floor.') }}
            <x-slot:actions><x-ui.button :href="route('tables.create')" icon="plus">{{ __('New table') }}</x-ui.button></x-slot:actions>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('Table') }}</th>
                    <th scope="col" class="text-right">{{ __('Seats') }}</th>
                    <th scope="col" class="text-right">{{ __('Position') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('In use') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </x-slot:head>
            @foreach ($tables as $table)
                <tr>
                    <td class="font-display text-xl font-semibold text-fg">{{ $table->label }}</td>
                    <td class="text-right text-muted tabular">{{ $table->capacity ?? '—' }}</td>
                    <td class="text-right text-muted tabular">{{ $table->sort_order }}</td>
                    <td><x-ui.status-badge :status="$table->status" /></td>
                    <td><x-ui.status-badge :status="$table->is_active ? 'active' : 'inactive'" :label="$table->is_active ? __('In use') : __('Out of use')" /></td>
                    <td>
                        <div class="flex justify-end gap-1">
                            <x-ui.button :href="route('tables.edit', $table)" variant="ghost">{{ __('Edit') }}<span class="sr-only"> {{ $table->label }}</span></x-ui.button>
                            <form method="POST" action="{{ route('tables.active', $table) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $table->is_active ? '0' : '1' }}">
                                <x-ui.button type="submit" variant="ghost">
                                    {{ $table->is_active ? __('Take out of use') : __('Put back in use') }}<span class="sr-only"> {{ $table->label }}</span>
                                </x-ui.button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layouts.app>
