<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Activity log') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('activity-logs.index') }}" class="grid gap-4 bg-white p-4 shadow-sm sm:rounded-lg sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <div>
                    <x-input-label for="user_id" :value="__('User')" />
                    <select id="user_id" name="user_id" class="mt-1 block min-h-12 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('Everyone') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }} ({{ $user->username }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="action" :value="__('Action')" />
                    <select id="action" name="action" class="mt-1 block min-h-12 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('All actions') }}</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('action')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="from" :value="__('From')" />
                    <x-text-input id="from" name="from" type="date" class="mt-1 block w-full" :value="$filters['from'] ?? ''" />
                    <x-input-error :messages="$errors->get('from')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="to" :value="__('To')" />
                    <x-text-input id="to" name="to" type="date" class="mt-1 block w-full" :value="$filters['to'] ?? ''" />
                    <x-input-error :messages="$errors->get('to')" class="mt-2" />
                </div>
                <div class="flex gap-2">
                    <x-primary-button>{{ __('Filter') }}</x-primary-button>
                    <a href="{{ route('activity-logs.index') }}" class="inline-flex min-h-12 items-center px-3 text-sm text-gray-700 hover:underline">{{ __('Reset') }}</a>
                </div>
            </form>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('When') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('User') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Action') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Record') }}</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Details') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="px-4 py-2 text-gray-900">{{ $log->user_name ?? __('System') }}</td>
                                <td class="px-4 py-2 font-mono text-gray-900">{{ $log->action }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                                <td class="px-4 py-1 text-right">
                                    <a href="{{ route('activity-logs.show', $log) }}" class="inline-flex min-h-12 items-center px-3 font-medium text-indigo-700 hover:underline">
                                        {{ __('View') }}<span class="sr-only"> {{ $log->action }}</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">{{ __('No activity matches these filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $logs->links() }}
        </div>
    </div>
</x-app-layout>
