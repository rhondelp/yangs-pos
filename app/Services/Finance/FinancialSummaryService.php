<?php

namespace App\Services\Finance;

use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Profit and loss from the records themselves (ARCHITECTURE §3.6). Read only. Every figure has one source table and is
 * dated by that table's own business date. Only closed tabs are sales. Inventory purchases are never in profit.
 */
class FinancialSummaryService
{
    public const BREAKDOWNS = ['day', 'week', 'month', 'year', 'product', 'category', 'cashier', 'payment_method'];

    private const PERIOD_FIGURES = ['net_sales', 'cogs', 'gross_profit', 'operating_expenses', 'inventory_losses', 'net_profit'];

    /** Units still sold on a line: ordered minus every void (returned or wasted). */
    private const SOLD = '(oi.quantity - coalesce(v.quantity, 0))';

    /**
     * @return array{gross_sales: string, discounts: string, refunds: string, net_sales: string, cogs: string, gross_profit: string,
     *               operating_expenses: string, inventory_losses: string, net_profit: string, inventory_purchases: string, items_without_cost: int}
     */
    public function summary(string $from, string $to): array
    {
        return $this->byBucket($from, $to, fn () => "'all'")['all'] ?? $this->derive([]);
    }

    /**
     * Rows for one view of the range, and a footer that reconciles them to the summary's net sales.
     * Row keys per $by: see docs/superpowers/plans/2026-11-23-profit-and-loss.md, Task 2.
     *
     * @return array{rows: list<array<string, mixed>>, footer: array<string, string>}
     */
    public function breakdown(string $from, string $to, string $by): array
    {
        return match ($by) {
            'day', 'week', 'month', 'year' => $this->periods($from, $to, $by),
            'product', 'category' => $this->products($from, $to, $by),
            'cashier' => $this->cashiers($from, $to),
            'payment_method' => $this->paymentMethods($from, $to),
            default => throw new InvalidArgumentException("Unknown breakdown [{$by}]."),
        };
    }

    private function periods(string $from, string $to, string $by): array
    {
        $bucket = match ($by) {
            'day' => fn (string $d) => "{$d}::text",
            'week' => fn (string $d) => "to_char({$d}, 'IYYY-\"W\"IW')",
            'month' => fn (string $d) => "to_char({$d}, 'YYYY-MM')",
            'year' => fn (string $d) => "to_char({$d}, 'YYYY')",
        };
        $rows = [];
        foreach ($this->byBucket($from, $to, $bucket) as $period => $figures) {
            $rows[] = ['period' => (string) $period, 'label' => $this->periodLabel($by, (string) $period), ...array_intersect_key($figures, array_flip(self::PERIOD_FIGURES))];
        }

        return ['rows' => $rows, 'footer' => array_intersect_key($this->summary($from, $to), array_flip(self::PERIOD_FIGURES))];
    }

    private function periodLabel(string $by, string $period): string
    {
        return match ($by) {
            'day' => CarbonImmutable::parse($period)->format('D, M j, Y'),
            'week' => (function () use ($period) {
                $monday = CarbonImmutable::now()->setISODate((int) substr($period, 0, 4), (int) substr($period, 6));

                return $monday->format('M j').' – '.$monday->addDays(6)->format('M j, Y');
            })(),
            'month' => CarbonImmutable::parse($period.'-01')->format('F Y'),
            'year' => $period,
        };
    }

    /** Sales after line discounts, before bill discounts and refunds; the footer takes those off to reach net sales. */
    private function products(string $from, string $to, string $by): array
    {
        $sold = self::SOLD;
        $query = $this->soldLines()->whereBetween('s.business_date', [$from, $to])
            ->join('products as p', 'p.id', '=', 'oi.product_id') // the query builder ignores soft deletes: archived ones stay
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->selectRaw("sum({$sold}) as quantity, sum(oi.line_total - coalesce(v.amount, 0)) as sales, sum({$sold} * coalesce(oi.unit_cost, 0)) as cogs")
            ->selectRaw("sum(case when oi.unit_cost is null then {$sold} else 0 end) as quantity_without_cost")
            ->havingRaw("sum({$sold}) > 0")
            ->orderByDesc('sales');
        $by === 'product'
            ? $query->addSelect('oi.product_id', 'oi.product_variant_id as variant_id', 'c.name as category')
                ->selectRaw('(array_agg(oi.product_name order by oi.id desc))[1] as product_name, (array_agg(oi.variant_name order by oi.id desc))[1] as variant_name')
                ->groupBy('oi.product_id', 'oi.product_variant_id', 'c.name')->orderBy('product_name')
            : $query->addSelect('c.id as category_id', 'c.name')->groupBy('c.id', 'c.name')->orderBy('c.name');

        $rows = [];
        $sales = '0.00';
        $cogs = '0.00';
        foreach ($query->get() as $row) {
            $line = ['sales' => bcadd((string) $row->sales, '0', 2), 'cogs' => bcadd((string) $row->cogs, '0', 2)];
            $profit = Money::sub($line['sales'], $line['cogs']);
            $identity = $by === 'product'
                ? ['product_id' => $row->product_id, 'variant_id' => $row->variant_id,
                    'name' => $row->variant_name === null ? $row->product_name : "{$row->product_name} – {$row->variant_name}", 'category' => $row->category]
                : ['category_id' => $row->category_id, 'name' => $row->name];
            $rows[] = [...$identity, 'quantity' => (int) $row->quantity, ...$line, 'gross_profit' => $profit,
                'margin' => bccomp($line['sales'], '0', 2) > 0 ? Money::percentOf($profit, $line['sales']) : null,
                'quantity_without_cost' => (int) $row->quantity_without_cost];
            $sales = Money::add($sales, $line['sales']);
            $cogs = Money::add($cogs, $line['cogs']);
        }
        $summary = $this->summary($from, $to);

        return ['rows' => $rows, 'footer' => [
            'sales' => $sales,
            'bill_discounts' => Money::sub($sales, bcadd((string) $this->closedTabs($from, $to)->sum('net_total'), '0', 2)),
            'refunds' => $summary['refunds'],
            'net_sales' => $summary['net_sales'],
            'cogs' => $cogs,
            'gross_profit' => Money::sub($summary['net_sales'], $cogs),
        ]];
    }

    /** Closed tabs by who closed them (took the final payment). */
    private function cashiers(string $from, string $to): array
    {
        $rows = $this->closedTabs($from, $to)->join('users as u', 'u.id', '=', 'table_sessions.closed_by')
            ->selectRaw('u.id as user_id, u.name, count(*) as tabs, sum(table_sessions.net_total) as sales')
            ->groupBy('u.id', 'u.name')->orderByDesc('sales')->orderBy('u.name')->get()
            ->map(fn ($r) => ['user_id' => $r->user_id, 'name' => $r->name, 'tabs' => (int) $r->tabs, 'sales' => bcadd((string) $r->sales, '0', 2)])->all();
        $summary = $this->summary($from, $to);

        return ['rows' => $rows, 'footer' => ['sales' => Money::add($summary['net_sales'], $summary['refunds']), 'refunds' => $summary['refunds'], 'net_sales' => $summary['net_sales']]];
    }

    /** Payments of closed tabs by their latest corrected method, minus refunds by the method the money went back through. */
    private function paymentMethods(string $from, string $to): array
    {
        $effective = 'coalesce((select pc.new_payment_method_id from payment_corrections pc where pc.payment_id = p.id order by pc.id desc limit 1), p.payment_method_id)';
        $payments = DB::table('payments as p')->join('table_sessions as s', 's.id', '=', 'p.table_session_id')
            ->where('s.status', 'closed')->whereBetween('s.business_date', [$from, $to])
            ->selectRaw("{$effective} as method_id, sum(p.amount) as total")->groupByRaw('1')->pluck('total', 'method_id');
        $refunds = DB::table('refunds')->whereBetween('business_date', [$from, $to])
            ->selectRaw('payment_method_id as method_id, sum(amount) as total')->groupBy('payment_method_id')->pluck('total', 'method_id');

        $rows = [];
        $footer = ['payments' => '0.00', 'refunds' => '0.00'];
        $methods = DB::table('payment_methods')->whereIn('id', $payments->keys()->merge($refunds->keys()))->orderBy('sort_order')->orderBy('id')->get(['id', 'name']);
        foreach ($methods as $method) {
            $paid = bcadd((string) ($payments[$method->id] ?? '0'), '0', 2);
            $refunded = bcadd((string) ($refunds[$method->id] ?? '0'), '0', 2);
            $rows[] = ['payment_method_id' => $method->id, 'name' => $method->name, 'payments' => $paid, 'refunds' => $refunded, 'net' => Money::sub($paid, $refunded)];
            $footer = ['payments' => Money::add($footer['payments'], $paid), 'refunds' => Money::add($footer['refunds'], $refunded)];
        }

        return ['rows' => $rows, 'footer' => [...$footer, 'net_sales' => Money::sub($footer['payments'], $footer['refunds'])]];
    }

    private function closedTabs(string $from, string $to): Builder
    {
        return DB::table('table_sessions')->where('table_sessions.status', 'closed')->whereBetween('table_sessions.business_date', [$from, $to]);
    }

    /**
     * Every figure grouped by a bucket of its own date column, then the profit lines derived per bucket.
     *
     * @param  Closure(string): string  $bucket  SQL expression for a date column, e.g. its month
     * @return array<string, array<string, string|int>>
     */
    private function byBucket(string $from, string $to, Closure $bucket): array
    {
        $parts = [];
        $add = function (Builder $query, string $date, array $sums) use (&$parts, $from, $to, $bucket) {
            $select = [$bucket($date).' as bucket'];
            foreach ($sums as $key => $expression) {
                $select[] = "coalesce(sum({$expression}), 0) as {$key}";
            }
            $rows = $query->whereBetween($date, [$from, $to])->selectRaw(implode(', ', $select))->groupByRaw('1')->get();
            foreach ($rows as $row) {
                foreach (array_keys($sums) as $key) {
                    $parts[$row->bucket][$key] = bcadd($parts[$row->bucket][$key] ?? '0', (string) $row->{$key}, 2);
                }
            }
        };

        $add(DB::table('table_sessions')->where('status', 'closed'), 'business_date', ['gross_sales' => 'gross_total', 'discounts' => 'discount_total']);
        $add(DB::table('refunds'), 'business_date', ['refunds' => 'amount']);
        $add($this->soldLines(), 's.business_date', [
            'cogs' => self::SOLD.' * coalesce(oi.unit_cost, 0)',
            'items_without_cost' => 'case when oi.unit_cost is null then '.self::SOLD.' else 0 end',
        ]);
        $add(DB::table('expenses')->whereNull('voided_at'), 'expense_date', ['operating_expenses' => 'amount']);
        // Inventory losses: wasted voids, poured items cancelled before payment, and stock-outs.
        $add(DB::table('order_voids as ov')->join('order_items as oi', 'oi.id', '=', 'ov.order_item_id')->where('ov.returned_to_stock', false),
            'ov.business_date', ['inventory_losses' => 'ov.quantity * coalesce(oi.unit_cost, 0)']);
        $add(DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')->where('oi.status', 'cancelled')->where('oi.returned_to_stock', false),
            'o.business_date', ['inventory_losses' => 'oi.quantity * coalesce(oi.unit_cost, 0)']);
        // shortcut: every adjustment is a stock-out today; a "found" (stock-in) adjustment would need its sign here.
        $add(DB::table('inventory_movements')->where('type', 'adjustment'), 'business_date', ['inventory_losses' => 'total_cost']);
        $add(DB::table('stock_receipts'), 'business_date', ['inventory_purchases' => 'total_cost']);

        ksort($parts);

        return array_map(fn (array $figures) => $this->derive($figures), $parts);
    }

    /** Active lines of closed tabs, with their voided quantity and amount as `v`. */
    private function soldLines(): Builder
    {
        $voids = DB::table('order_voids')->selectRaw('order_item_id, sum(quantity) as quantity, sum(amount) as amount')->groupBy('order_item_id');

        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('table_sessions as s', 's.id', '=', 'o.table_session_id')
            ->leftJoinSub($voids, 'v', 'v.order_item_id', '=', 'oi.id')
            ->where('s.status', 'closed')
            ->where('oi.status', 'active');
    }

    /** @param  array<string, string>  $f  raw sums */
    private function derive(array $f): array
    {
        $get = fn (string $key) => $f[$key] ?? '0.00';
        $net = Money::sub(Money::sub($get('gross_sales'), $get('discounts')), $get('refunds'));
        $grossProfit = Money::sub($net, $get('cogs'));

        return [
            'gross_sales' => $get('gross_sales'),
            'discounts' => $get('discounts'),
            'refunds' => $get('refunds'),
            'net_sales' => $net,
            'cogs' => $get('cogs'),
            'gross_profit' => $grossProfit,
            'operating_expenses' => $get('operating_expenses'),
            'inventory_losses' => $get('inventory_losses'),
            'net_profit' => Money::sub(Money::sub($grossProfit, $get('operating_expenses')), $get('inventory_losses')),
            'inventory_purchases' => $get('inventory_purchases'),
            'items_without_cost' => (int) $get('items_without_cost'),
        ];
    }
}
