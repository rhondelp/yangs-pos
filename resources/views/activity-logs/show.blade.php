@php
    $display = fn ($value) => match (true) {
        is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        is_bool($value) => $value ? 'true' : 'false',
        $value === null => '—',
        default => (string) $value,
    };
@endphp

<x-layouts.app :title="__('Activity #:id', ['id' => $log->id])">
    <x-ui.card class="max-w-4xl">
        <dl class="grid gap-4 text-base sm:grid-cols-2">
            <div><dt class="text-sm text-muted">{{ __('Action') }}</dt><dd class="text-fg">{{ $log->action }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('When') }}</dt><dd class="text-fg tabular">{{ $log->created_at->format('Y-m-d H:i:s') }} ({{ config('app.timezone') }})</dd></div>
            <div><dt class="text-sm text-muted">{{ __('User') }}</dt><dd class="text-fg">{{ $log->user_name ?? __('System') }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('Record') }}</dt><dd class="text-fg">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('Approved by (user id)') }}</dt><dd class="text-fg">{{ $log->approved_by ?? '—' }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('IP address') }}</dt><dd class="text-fg">{{ $log->ip_address ?? '—' }}</dd></div>
        </dl>
    </x-ui.card>

    <x-ui.table :caption="__('Changes')" class="max-w-4xl">
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('Field') }}</th>
                <th scope="col">{{ __('Old value') }}</th>
                <th scope="col">{{ __('New value') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($fields as $field)
            @php($changed = ($old[$field] ?? null) !== ($new[$field] ?? null))
            <tr @class(['bg-warning/10' => $changed])>
                <th scope="row" class="text-left font-normal text-fg">{{ $field }}</th>
                <td class="break-all text-muted">{{ array_key_exists($field, $old) ? $display($old[$field]) : '—' }}</td>
                <td class="break-all text-fg">{{ array_key_exists($field, $new) ? $display($new[$field]) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="py-6 text-center text-muted">{{ __('No values recorded for this action.') }}</td></tr>
        @endforelse
    </x-ui.table>

    <x-ui.button :href="route('activity-logs.index')" variant="secondary" icon="chevron-left">{{ __('Back to activity log') }}</x-ui.button>
</x-layouts.app>
