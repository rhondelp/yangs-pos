@php($method ??= null)
<x-ui.input name="name" :label="__('Name')" :value="$method?->name" required maxlength="60" />
<x-ui.input name="sort_order" type="number" min="0" inputmode="numeric" :label="__('Position')" :value="$method?->sort_order" :hint="__('Lower comes first on the bill screen.')" />
@unless ($method?->is_cash)
    <input type="hidden" name="requires_reference" value="0">
    <label for="requires_reference" class="flex min-h-touch items-start gap-3">
        <input id="requires_reference" name="requires_reference" type="checkbox" value="1"
               class="mt-0.5 size-6 rounded border-edge bg-raised text-accent focus:ring-accent" @checked(old('requires_reference', $method?->requires_reference ?? true))>
        <span>
            <span class="block text-base text-fg">{{ __('Ask for a reference number') }}</span>
            <span class="block text-sm text-muted">{{ __('For e-wallets and cards, so the payment can be traced.') }}</span>
        </span>
    </label>
@endunless
@if ($method)
    <input type="hidden" name="is_active" value="0">
    <label for="is_active" class="flex min-h-touch items-start gap-3">
        <input id="is_active" name="is_active" type="checkbox" value="1" @error('is_active') aria-invalid="true" aria-describedby="is_active-error" @enderror
               class="mt-0.5 size-6 rounded border-edge bg-raised text-accent focus:ring-accent" @checked(old('is_active', $method->is_active))>
        <span>
            <span class="block text-base text-fg">{{ __('Switched on') }}</span>
            <span class="block text-sm text-muted">{{ __('Off: hidden from the bill screen. Past payments keep it.') }}</span>
        </span>
    </label>
    @error('is_active')
        <p id="is_active-error" class="text-sm font-medium text-danger">{{ $message }}</p>
    @enderror
@endif
