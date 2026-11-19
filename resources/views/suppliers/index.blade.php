<x-layouts.app :title="__('Suppliers')">
    <x-ui.card :title="__('New supplier')">
        <form method="POST" action="{{ route('suppliers.store') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <x-ui.input name="name" :label="__('Name')" required maxlength="120" class="sm:col-span-2" />
            <x-ui.input name="contact_person" :label="__('Contact person')" maxlength="120" />
            <x-ui.input name="phone" type="tel" :label="__('Phone')" maxlength="40" />
            <div class="sm:col-span-2 lg:col-span-4">
                <x-ui.button type="submit" icon="plus">{{ __('Add supplier') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('Name') }}</th>
                <th scope="col">{{ __('Contact') }}</th>
                <th scope="col" class="text-right">{{ __('Stock-ins') }}</th>
                <th scope="col">{{ __('Status') }}</th>
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </x-slot:head>
        @forelse ($suppliers as $supplier)
            <tr>
                <td class="text-base text-fg">{{ $supplier->name }}</td>
                <td class="text-muted">{{ collect([$supplier->contact_person, $supplier->phone])->filter()->join(', ') ?: '—' }}</td>
                <td class="text-right text-muted tabular">{{ $supplier->receipts_count }}</td>
                <td><x-ui.status-badge :status="$supplier->trashed() ? 'archived' : 'active'" /></td>
                <td>
                    <div class="flex justify-end gap-1">
                        @if ($supplier->trashed())
                            <form method="POST" action="{{ route('suppliers.restore', $supplier) }}">
                                @csrf
                                <x-ui.button type="submit" variant="ghost">{{ __('Restore') }}<span class="sr-only"> {{ $supplier->name }}</span></x-ui.button>
                            </form>
                        @else
                            <x-ui.button :href="route('suppliers.edit', $supplier)" variant="ghost">{{ __('Edit') }}<span class="sr-only"> {{ $supplier->name }}</span></x-ui.button>
                            <form method="POST" action="{{ route('suppliers.archive', $supplier) }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="ghost">{{ __('Archive') }}<span class="sr-only"> {{ $supplier->name }}</span></x-ui.button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-6 text-center text-muted">{{ __('No suppliers yet. Add the companies you buy stock from.') }}</td></tr>
        @endforelse
    </x-ui.table>
</x-layouts.app>
