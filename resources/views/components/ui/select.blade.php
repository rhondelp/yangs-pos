@props([
    'name',
    'label' => null,
    'options' => [],      // value => label
    'selected' => null,
    'placeholder' => null,
    'hint' => null,
    'id' => null,
])

@php
    $id ??= $name;
    $error = isset($errors) ? $errors->first($name) : null;
    $current = (string) old($name, $selected);
    $describedBy = $error ? "{$id}-error" : ($hint ? "{$id}-hint" : null);
@endphp

<x-ui.field :id="$id" :label="$label" :hint="$hint" :error="$error" :required="(bool) $attributes->get('required')" :class="$attributes->get('class')">
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except('class')->merge(['class' => 'block min-h-touch w-full rounded-control border bg-raised px-3 text-base text-fg focus:border-accent focus:ring-accent '.($error ? 'border-danger' : 'border-edge')]) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected($current === (string) $value)>{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
</x-ui.field>
