@props(['status', 'label' => null])

@php
    $key = $status instanceof \BackedEnum ? $status->value : (string) $status;

    // [tone, label]. Tones are literal class strings so Tailwind can see them.
    [$tone, $text] = match ($key) {
        // Tables
        'available' => ['bg-success/15 text-success', __('Available')],
        'occupied' => ['bg-info/15 text-info', __('Occupied')],
        'awaiting_payment' => ['bg-warning/15 text-warning', __('Awaiting payment')],
        'reserved' => ['bg-reserved/15 text-reserved', __('Reserved')],
        'cleaning' => ['bg-raised text-muted', __('Cleaning')],
        'out_of_service' => ['bg-raised text-muted', __('Out of service')],
        // Sessions and orders
        'open' => ['bg-info/15 text-info', __('Open')],
        'billing' => ['bg-warning/15 text-warning', __('Bill presented')],
        'closed' => ['bg-success/15 text-success', __('Closed')],
        'cancelled' => ['bg-raised text-muted', __('Cancelled')],
        'merged' => ['bg-raised text-muted', __('Merged')],
        'voided' => ['bg-danger/15 text-danger', __('Voided')],
        // Payments
        'unpaid' => ['bg-danger/15 text-danger', __('Unpaid')],
        'partial' => ['bg-warning/15 text-warning', __('Partially paid')],
        'paid' => ['bg-success/15 text-success', __('Paid')],
        'refunded' => ['bg-raised text-muted', __('Refunded')],
        'partially_refunded' => ['bg-warning/15 text-warning', __('Partially refunded')],
        // Stock
        'in_stock' => ['bg-success/15 text-success', __('In stock')],
        'low_stock' => ['bg-warning/15 text-warning', __('Low stock')],
        'out_of_stock' => ['bg-danger/15 text-danger', __('Out of stock')],
        'negative_stock' => ['bg-danger/15 text-danger', __('Negative stock')],
        // Catalogue
        'hidden' => ['bg-raised text-muted', __('Hidden from menu')],
        'archived' => ['bg-raised text-muted', __('Archived')],
        // Accounts
        'active' => ['bg-success/15 text-success', __('Active')],
        'inactive' => ['bg-raised text-muted', __('Deactivated')],
        default => ['bg-raised text-muted', \Illuminate\Support\Str::headline($key)],
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-sm font-medium {$tone}"]) }}>
    {{ $label ?? $text }}
</span>
