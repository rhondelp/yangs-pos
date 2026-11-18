<x-layouts.app :title="__('Users')">
    <div class="flex justify-end">
        <x-ui.button :href="route('users.create')">{{ __('New user') }}</x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('Name') }}</th>
                <th scope="col">{{ __('Username') }}</th>
                <th scope="col">{{ __('Role') }}</th>
                <th scope="col">{{ __('Status') }}</th>
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </x-slot:head>
        @foreach ($users as $user)
            <tr>
                <td class="text-base text-fg">{{ $user->name }}</td>
                <td class="text-muted">{{ $user->username }}</td>
                <td class="text-muted">{{ $user->roles->first()?->name ?? '—' }}</td>
                <td><x-ui.status-badge :status="$user->is_active ? 'active' : 'inactive'" /></td>
                <td class="text-right">
                    @can('update', $user)
                        <x-ui.button :href="route('users.edit', $user)" variant="ghost">
                            {{ __('Edit') }}<span class="sr-only"> {{ $user->username }}</span>
                        </x-ui.button>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layouts.app>
