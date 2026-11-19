@php
    use App\Support\Money;
    use Carbon\CarbonImmutable;
    $width = $paper === 58 ? '48mm' : '72mm';
    $at = fn (string $iso) => CarbonImmutable::parse($iso)->timezone(config('app.timezone'))->format('M j, Y g:i A');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Receipt :number', ['number' => $receipt['receipt_number']]) }}</title>
    <style>
        /* "<width> auto" is invalid CSS and Chrome drops it; the script below swaps in the slip's real height. */
        @page { size: {{ $paper }}mm 297mm; margin: 0; }
        * { box-sizing: border-box; }
        /* On screen: the paper slip on the app's smoke background (design-system/YANGS/MASTER.md). */
        body { margin: 0; background: #1a1512; color: #000; font: 12px/1.35 ui-monospace, "Courier New", monospace; }
        .paper { width: {{ $width }}; margin: 16px auto; padding: 4mm 0; background: #fff; }
        .paper > * { padding: 0 2mm; }
        h1 { margin: 0; font-size: 15px; text-align: center; }
        .center { text-align: center; }
        .row { display: flex; justify-content: space-between; gap: 6px; }
        .row > :last-child { text-align: right; white-space: nowrap; }
        .muted { color: #333; }
        .rule { border-top: 1px dashed #000; margin: 6px 2mm; padding: 0; }
        .total { font-weight: 700; font-size: 14px; }
        .actions { display: flex; justify-content: center; gap: 12px; margin: 16px; font-family: system-ui, sans-serif; }
        .actions a, .actions button { min-height: 48px; min-width: 120px; padding: 0 20px; border: 0; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
        .actions button { background: #f2a93b; color: #1a1512; }
        .actions a { background: #30271f; color: #f5ede3; border: 1px solid #7a6756; }
        .actions :focus-visible { outline: 2px solid #f2a93b; outline-offset: 2px; }
        @media print {
            body { background: #fff; }
            .paper { margin: 0; width: {{ $width }}; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <main class="paper" aria-label="{{ __('Receipt') }}">
        <h1>{{ $receipt['business']['name'] }}</h1>
        @if ($receipt['business']['address'] !== '')
            <p class="center" style="margin:2px 0">{{ $receipt['business']['address'] }}</p>
        @endif
        @if ($receipt['business']['tin'] !== '')
            <p class="center" style="margin:2px 0">{{ __('TIN') }} {{ $receipt['business']['tin'] }}</p>
        @endif
        @if ($reprint)
            <p class="center" style="margin:4px 0;font-weight:700">** {{ __('REPRINT') }} **</p>
        @endif

        <div class="rule"></div>
        <div class="row"><span>{{ __('Receipt') }}</span><span>{{ $receipt['receipt_number'] }}</span></div>
        <div class="row"><span>{{ __('Table') }}</span><span>{{ $receipt['table'] }}</span></div>
        <div class="row"><span>{{ __('Paid') }}</span><span>{{ $at($receipt['closed_at']) }}</span></div>
        @if ($receipt['cashier'])
            <div class="row"><span>{{ __('Cashier') }}</span><span>{{ $receipt['cashier'] }}</span></div>
        @endif
        <div class="rule"></div>

        @foreach ($receipt['items'] as $item)
            <div>{{ $item['name'] }}</div>
            <div class="row muted"><span>&nbsp;&nbsp;{{ $item['quantity'] }} × {{ Money::format($item['unit_price']) }}</span><span>{{ Money::format(Money::add($item['line_total'], $item['discount'])) }}</span></div>
            @if (bccomp($item['discount'], '0', 2) > 0)
                <div class="row muted"><span>&nbsp;&nbsp;{{ __('Less discount') }}</span><span>-{{ Money::format($item['discount']) }}</span></div>
            @endif
        @endforeach

        <div class="rule"></div>
        <div class="row"><span>{{ __('Subtotal') }}</span><span>{{ Money::format($receipt['gross']) }}</span></div>
        @if (bccomp($receipt['discount'], '0', 2) > 0)
            <div class="row"><span>{{ __('Discount') }}@if ($receipt['discount_reason']) ({{ $receipt['discount_reason'] }})@endif</span><span>-{{ Money::format($receipt['discount']) }}</span></div>
        @endif
        <div class="row total"><span>{{ __('TOTAL') }}</span><span>{{ Money::format($receipt['total']) }}</span></div>
        <div class="rule"></div>

        @foreach ($receipt['payments'] as $payment)
            <div class="row"><span>{{ $payment['method'] }}@if ($payment['reference_no']) #{{ $payment['reference_no'] }}@endif</span><span>{{ Money::format($payment['tendered'] ?? $payment['amount']) }}</span></div>
        @endforeach
        @if (bccomp($receipt['change'], '0', 2) > 0)
            <div class="row"><span>{{ __('Change') }}</span><span>{{ Money::format($receipt['change']) }}</span></div>
        @endif

        @if ($receipt['business']['footer'] !== '')
            <div class="rule"></div>
            <p class="center" style="margin:4px 0">{{ $receipt['business']['footer'] }}</p>
        @endif
    </main>

    <nav class="actions" aria-label="{{ __('Receipt actions') }}">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <a href="{{ route('floor') }}">{{ __('Back to floor') }}</a>
    </nav>
    <script>
        // Page height = slip height, so a roll printer feeds only what was printed.
        (() => {
            const slip = document.querySelector('.paper');
            const style = document.createElement('style');
            const fit = () => (style.textContent = `@page { size: {{ $paper }}mm ${Math.ceil((slip.offsetHeight * 25.4) / 96) + 2}mm; margin: 0; }`);
            document.head.append(style);
            fit();
            window.addEventListener('beforeprint', fit);
        })();
    </script>
</body>
</html>
