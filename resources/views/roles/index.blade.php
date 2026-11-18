<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Roles and permissions') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</p>
            @endif
            <x-input-error :messages="$errors->all()" />

            <p class="text-sm text-gray-600">{{ __('Tick what each role may do. The Owner role always has every permission. Changes apply on each user\'s next page load.') }}</p>

            <form method="POST" action="{{ route('roles.update') }}">
                @csrf
                @method('PUT')

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-gray-50 text-gray-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-medium">{{ __('Permission') }}</th>
                                @foreach ($roles as $role)
                                    <th scope="col" class="px-2 py-3 text-center font-medium">{{ $role->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        @foreach ($groups as $group => $permissions)
                            <tbody class="divide-y divide-gray-100 border-t border-gray-200">
                                <tr class="bg-gray-50">
                                    <th scope="colgroup" colspan="{{ $roles->count() + 1 }}" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $group }}</th>
                                </tr>
                                @foreach ($permissions as $permission)
                                    <tr>
                                        <th scope="row" class="px-4 py-1 text-left font-normal text-gray-900">
                                            {{ $permission->label() }}
                                            <span class="block text-xs text-gray-500">{{ $permission->value }}</span>
                                        </th>
                                        @foreach ($roles as $role)
                                            @php($locked = $role->name === \App\Enums\RoleName::Owner->value)
                                            <td class="px-2 py-1 text-center">
                                                <label class="inline-flex h-12 w-12 items-center justify-center">
                                                    <span class="sr-only">{{ $role->name }}: {{ $permission->label() }}</span>
                                                    <input type="checkbox"
                                                           class="h-6 w-6 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50"
                                                           @if ($locked)
                                                               checked disabled
                                                           @else
                                                               name="permissions[{{ $role->id }}][]"
                                                               value="{{ $permission->value }}"
                                                               @checked($role->permissions->contains('name', $permission->value))
                                                           @endif
                                                    >
                                                </label>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>

                <div class="mt-4">
                    <x-primary-button>{{ __('Save permissions') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
