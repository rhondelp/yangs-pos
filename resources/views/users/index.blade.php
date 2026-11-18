<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Users') }}</h2>
            <a href="{{ route('users.create') }}" class="inline-flex min-h-12 items-center rounded-md bg-gray-800 px-4 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ __('New user') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p role="status" class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</p>
            @endif

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Username') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Role') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-4 py-3 text-gray-900">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $user->username }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $user->roles->first()?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($user->is_active)
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">{{ __('Active') }}</span>
                                    @else
                                        <span class="rounded-full bg-gray-200 px-2 py-1 text-xs font-medium text-gray-700">{{ __('Deactivated') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @can('update', $user)
                                        <a href="{{ route('users.edit', $user) }}" class="inline-flex min-h-12 items-center px-3 font-medium text-indigo-700 hover:underline">
                                            {{ __('Edit') }}<span class="sr-only"> {{ $user->username }}</span>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
