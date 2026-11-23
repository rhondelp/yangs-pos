<?php

namespace Tests\Feature\Finance;

use App\Models\TableSession;
use App\Services\Finance\FinancialSummaryService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\BuildsFinanceFixture;
use Tests\TestCase;

class FinancialSummaryServiceTest extends TestCase
{
    use BuildsFinanceFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildFinanceFixture();
    }

    private function summary(string $from, string $to): array
    {
        return app(FinancialSummaryService::class)->summary($from, $to);
    }

    public function test_the_statement_matches_the_hand_calculation(): void
    {
        $this->assertSame([
            'gross_sales' => '1050.00', 'discounts' => '112.00', 'refunds' => '50.00', 'net_sales' => '888.00',
            'cogs' => '300.00', 'gross_profit' => '588.00', 'operating_expenses' => '6500.00', 'inventory_losses' => '190.00',
            'net_profit' => '-6102.00', 'inventory_purchases' => '1000.00', 'items_without_cost' => 1,
        ], $this->summary('2026-11-20', '2026-11-21'));

        $this->assertSame([
            'gross_sales' => '1050.00', 'discounts' => '112.00', 'refunds' => '0.00', 'net_sales' => '938.00',
            'cogs' => '300.00', 'gross_profit' => '638.00', 'operating_expenses' => '1500.00', 'inventory_losses' => '190.00',
            'net_profit' => '-1052.00', 'inventory_purchases' => '1000.00', 'items_without_cost' => 1,
        ], $this->summary('2026-11-20', '2026-11-20'));

        $this->assertSame([
            'gross_sales' => '0.00', 'discounts' => '0.00', 'refunds' => '50.00', 'net_sales' => '-50.00',
            'cogs' => '0.00', 'gross_profit' => '-50.00', 'operating_expenses' => '5000.00', 'inventory_losses' => '0.00',
            'net_profit' => '-5050.00', 'inventory_purchases' => '0.00', 'items_without_cost' => 0,
        ], $this->summary('2026-11-21', '2026-11-21'));
    }

    public function test_inventory_purchases_are_not_operating_expenses(): void
    {
        $day = $this->summary('2026-11-20', '2026-11-20');

        $this->assertSame('1500.00', $day['operating_expenses']); // the 1000.00 stock-in is not in it
        $this->assertSame('1000.00', $day['inventory_purchases']);
        $this->assertSame($day['net_profit'], Money::sub(Money::sub($day['gross_profit'], $day['operating_expenses']), $day['inventory_losses']));
    }

    public function test_refunds_lower_sales_not_cogs(): void
    {
        $refundDay = $this->summary('2026-11-21', '2026-11-21');

        $this->assertSame('50.00', $refundDay['refunds']);
        $this->assertSame('0.00', $refundDay['cogs']);
        $this->assertSame($this->summary('2026-11-20', '2026-11-20')['cogs'], $this->summary('2026-11-20', '2026-11-21')['cogs']);
    }

    public function test_a_tab_counts_on_the_day_it_is_closed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-22 21:00', 'Asia/Manila'));
        $tab = $this->openTab('T7', $this->cashier);
        $this->line($tab, 'Beer', 1);
        $this->travelTo(CarbonImmutable::parse('2026-11-23 21:00', 'Asia/Manila')); // still open a day later
        $this->pay($tab, $this->cashier, 'cash', '100');

        $this->assertSame('2026-11-23', $tab->fresh()->business_date->toDateString());
        $this->assertSame(['0.00', '0.00'], array_values(array_intersect_key($this->summary('2026-11-22', '2026-11-22'), array_flip(['gross_sales', 'cogs']))));
        $this->assertSame(['100.00', '40.00'], array_values(array_intersect_key($this->summary('2026-11-23', '2026-11-23'), array_flip(['gross_sales', 'cogs']))));
    }

    public function test_voids_are_not_counted_twice(): void
    {
        $closed = (string) TableSession::where('status', 'closed')->sum('net_total');

        $this->assertSame('938.00', $closed); // the 150 and 90 voided are already out
        $this->assertSame(Money::sub($closed, '50.00'), $this->summary('2026-11-20', '2026-11-21')['net_sales']);
    }
}
