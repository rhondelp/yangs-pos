{{-- Supervisor approval fields, shown when the require_approval_pin setting is on. $model: Alpine object to bind (bill screen); $prefix: unique id prefix. --}}
@php
    $model ??= null;
    $prefix ??= 'approver';
    $input = 'block min-h-touch w-full rounded-control border border-edge bg-raised px-3 text-base text-fg focus:border-accent focus:ring-accent';
@endphp
<fieldset class="grid gap-3 rounded-control border border-line p-3 sm:grid-cols-2">
    <legend class="px-1 text-sm font-medium text-muted">{{ __('Supervisor approval') }}</legend>
    <div>
        <label for="{{ $prefix }}-username" class="mb-1 block text-sm font-medium text-muted">{{ __('Supervisor username') }}</label>
        <input id="{{ $prefix }}-username" name="approver_username" type="text" required autocomplete="off" autocapitalize="none" maxlength="50"
               @if ($model) x-model="{{ $model }}.approver_username" @else value="{{ old('approver_username') }}" @endif class="{{ $input }}">
    </div>
    <div>
        <label for="{{ $prefix }}-pin" class="mb-1 block text-sm font-medium text-muted">{{ __('Supervisor PIN') }}</label>
        <input id="{{ $prefix }}-pin" name="approver_pin" type="password" inputmode="numeric" required autocomplete="off" maxlength="6"
               @if ($model) x-model="{{ $model }}.approver_pin" @endif class="{{ $input }}">
    </div>
</fieldset>
