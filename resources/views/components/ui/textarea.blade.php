@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'id' => null, 'rows' => 3])

@php
    $id ??= $name;
    $error = isset($errors) ? $errors->first($name) : null;
    $describedBy = $error ? "{$id}-error" : ($hint ? "{$id}-hint" : null);
@endphp

<x-ui.field :id="$id" :label="$label" :hint="$hint" :error="$error" :required="(bool) $attributes->get('required')" :class="$attributes->get('class')">
    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except('class')->merge(['class' => 'block w-full rounded-control border bg-raised px-3 py-3 text-base text-fg placeholder:text-muted focus:border-accent focus:ring-accent '.($error ? 'border-danger' : 'border-edge')]) }}
    >{{ old($name, $value) }}</textarea>
</x-ui.field>
