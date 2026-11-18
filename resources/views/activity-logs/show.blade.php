@php
    $display = fn ($value) => match (true) {
        is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        is_bool($value) => $value ? 'true' : 'false',
        $value === null => '—',
        default => (string) $value,
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Activity') }} #{{ $log->id }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <dl class="grid gap-4 bg-white p-6 text-sm shadow-sm sm:grid-cols-2 sm:rounded-lg">
                <div><dt class="text-gray-500">{{ __('Action') }}</dt><dd class="font-mono text-gray-900">{{ $log->action }}</dd></div>
                <div><dt class="text-gray-500">{{ __('When') }}</dt><dd class="text-gray-900">{{ $log->created_at->format('Y-m-d H:i:s') }} ({{ config('app.timezone') }})</dd></div>
                <div><dt class="text-gray-500">{{ __('User') }}</dt><dd class="text-gray-900">{{ $log->user_name ?? __('System') }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Record') }}</dt><dd class="text-gray-900">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Approved by (user id)') }}</dt><dd class="text-gray-900">{{ $log->approved_by ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">{{ __('IP address') }}</dt><dd class="text-gray-900">{{ $log->ip_address ?? '—' }}</dd></div>
            </dl>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <caption class="px-4 py-3 text-left font-medium text-gray-700">{{ __('Changes') }}</caption>
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Field') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Old value') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('New value') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($fields as $field)
                            @php($changed = ($old[$field] ?? null) !== ($new[$field] ?? null))
                            <tr @class(['bg-amber-50' => $changed])>
                                <th scope="row" class="px-4 py-2 text-left font-mono font-normal text-gray-900">{{ $field }}</th>
                                <td class="px-4 py-2 break-all text-gray-700">{{ array_key_exists($field, $old) ? $display($old[$field]) : '—' }}</td>
                                <td class="px-4 py-2 break-all text-gray-900">{{ array_key_exists($field, $new) ? $display($new[$field]) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">{{ __('No values recorded for this action.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <a href="{{ url()->previous() === url()->current() ? route('activity-logs.index') : url()->previous() }}" class="inline-flex min-h-12 items-center text-sm text-indigo-700 hover:underline">{{ __('Back to activity log') }}</a>
        </div>
    </div>
</x-app-layout>
