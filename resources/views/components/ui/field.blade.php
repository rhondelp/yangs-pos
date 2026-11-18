@props(['id', 'label' => null, 'hint' => null, 'error' => null, 'required' => false])

{{-- Shared label / hint / error wrapper for input, select and textarea. --}}
<div {{ $attributes }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-fg">
            {{ $label }}@if ($required)<span class="text-muted" aria-hidden="true"> *</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint && ! $error)
        <p id="{{ $id }}-hint" class="mt-1.5 text-sm text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm font-medium text-danger">{{ $error }}</p>
    @endif
</div>
