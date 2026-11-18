@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'id' => null,
    'errorKey' => null,
])

@php
    $id ??= $name;
    $error = isset($errors) ? $errors->first($errorKey ?? $name) : null;
    // Passwords are never echoed back into the page.
    $current = $type === 'password' ? null : old($name, $value);
    $describedBy = $error ? "{$id}-error" : ($hint ? "{$id}-hint" : null);
@endphp

<x-ui.field :id="$id" :label="$label" :hint="$hint" :error="$error" :required="(bool) $attributes->get('required')" :class="$attributes->get('class')">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($current !== null) value="{{ $current }}" @endif
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except('class')->merge(['class' => 'block min-h-touch w-full rounded-control border bg-raised px-3 text-base text-fg placeholder:text-muted focus:border-accent focus:ring-accent '.($error ? 'border-danger' : 'border-edge')]) }}
    >
</x-ui.field>
