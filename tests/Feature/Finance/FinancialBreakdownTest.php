<?php

namespace Tests\Feature\Finance;

use App\Models\Category;
use App\Models\Payment;
use App\Services\Catalog\ProductService;
use App\Services\Corrections\PaymentCorrectionService;
use App\Services\Finance\FinancialSummaryService;
use App\Services\Orders\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\BuildsFinanceFixture;
use Tests\TestCase;

class FinancialBreakdownTest extends TestCase
{
    use BuildsFinanceFixture;
    use RefreshDatabase;

    private const SHARED = ['net_sales', 'cogs', 'gross_profit', 'operating_expenses', 'inventory_losses', 'net_profit'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildFinanceFixture();
    }

    private function breakdown(string $by, string $from = '2026-11-20', string $to = '2026-11-21'): array
    {
        return app(FinancialSummaryService::class)->breakdown($from, $to, $by);
    }

    /** @return array<string, array> rows keyed by $key */
    private function rows(string $by, string $key = 'name'): array
    {
        return array_column($this->breakdown($by)['rows'], null, $key);
    }

    public function test_periods_add_up_to_the_statement(): void
    {
        $summary = array_intersect_key(app(FinancialSummaryService::class)->summary('2026-11-20', '2026-11-21'), array_flip(self::SHARED));

        $days = $this->breakdown('day');
        $this->assertSame(['2026-11-20', '2026-11-21'], array_column($days['rows'], 'period'));
        $this->assertSame(['938.00', '-50.00'], array_column($days['rows'], 'net_sales'));
        $this->assertSame(['-1052.00', '-5050.00'], array_column($days['rows'], 'net_profit'));

        foreach (['week' => '2026-W47', 'month' => '2026-11', 'year' => '2026'] as $by => $period) {
            $result = $this->breakdown($by);
            $this->assertSame([$period], array_column($result['rows'], 'period'), $by);
            $this->assertSame('-6102.00', $result['rows'][0]['net_profit'], $by);
        }
        foreach (['day', 'week', 'month', 'year'] as $by) {
            $this->assertSame($summary, $this->breakdown($by)['footer'], $by);
        }
    }

    public function test_week_labels_start_on_monday_in_any_locale(): void
    {
        CarbonImmutable::setLocale('en_PH'); // Sunday-first locale
        try {
            $this->assertSame('Nov 16 – Nov 22, 2026', $this->breakdown('week')['rows'][0]['label']);
        } finally {
            CarbonImmutable::setLocale(config('app.locale'));
        }
    }

    public function test_products_with_margins_reconcile_to_net_sales(): void
    {
        $result = $this->breakdown('product');
        $rows = array_column($result['rows'], null, 'name');

        $this->assertSame(['Beer', 'Gin Tonic', 'Sisig'], array_column($result['rows'], 'name')); // highest sales first
        $this->assertSame([5, '470.00', '200.00', '270.00', '57.45', 0], array_values(array_intersect_key($rows['Beer'], array_flip(['quantity', 'sales', 'cogs', 'gross_profit', 'margin', 'quantity_without_cost']))));
        $this->assertSame([2, '300.00', '100.00', '200.00', '66.67', 0], array_values(array_intersect_key($rows['Gin Tonic'], array_flip(['quantity', 'sales', 'cogs', 'gross_profit', 'margin', 'quantity_without_cost']))));
        $this->assertSame([1, '250.00', '0.00', '250.00', '100.00', 1], array_values(array_intersect_key($rows['Sisig'], array_flip(['quantity', 'sales', 'cogs', 'gross_profit', 'margin', 'quantity_without_cost']))));
        $this->assertSame('Cocktails', $rows['Gin Tonic']['category']);
        $this->assertSame(['sales' => '1020.00', 'bill_discounts' => '82.00', 'refunds' => '50.00', 'net_sales' => '888.00', 'cogs' => '300.00', 'gross_profit' => '588.00'], $result['footer']);
    }

    public function test_categories(): void
    {
        $result = $this->breakdown('category');
        $rows = array_column($result['rows'], null, 'name');

        $this->assertSame(['470.00', '200.00'], [$rows['Beer']['sales'], $rows['Beer']['cogs']]);
        $this->assertSame(['300.00', '100.00'], [$rows['Cocktails']['sales'], $rows['Cocktails']['cogs']]);
        $this->assertSame(['250.00', '0.00', 1], [$rows['Food']['sales'], $rows['Food']['cogs'], $rows['Food']['quantity_without_cost']]);
        $this->assertSame('888.00', $result['footer']['net_sales']);
        $this->assertSame('82.00', $result['footer']['bill_discounts']);
    }

    public function test_cashiers(): void
    {
        $result = $this->breakdown('cashier');
        $rows = array_column($result['rows'], null, 'name');

        $this->assertSame([1, '738.00'], [$rows['Carlo Cashier']['tabs'], $rows['Carlo Cashier']['sales']]);
        $this->assertSame([1, '200.00'], [$rows['Olivia Owner']['tabs'], $rows['Olivia Owner']['sales']]);
        $this->assertSame(['sales' => '938.00', 'refunds' => '50.00', 'net_sales' => '888.00'], $result['footer']);
    }

    public function test_payment_methods_use_the_corrected_method(): void
    {
        $result = $this->breakdown('payment_method');
        $rows = array_column($result['rows'], null, 'name');

        $this->assertSame(['738.00', '0.00', '738.00'], [$rows['GCash']['payments'], $rows['GCash']['refunds'], $rows['GCash']['net']]);
        $this->assertSame(['200.00', '50.00', '150.00'], [$rows['Cash']['payments'], $rows['Cash']['refunds'], $rows['Cash']['net']]);
        $this->assertSame(['payments' => '938.00', 'refunds' => '50.00', 'net_sales' => '888.00'], $result['footer']);
    }

    public function test_an_empty_range_is_all_zero(): void
    {
        $service = app(FinancialSummaryService::class);
        $summary = $service->summary('2026-01-01', '2026-01-31');

        $this->assertSame(['0.00'], array_values(array_unique(array_diff_key($summary, ['items_without_cost' => 0]))));
        $this->assertSame(0, $summary['items_without_cost']);
        foreach (FinancialSummaryService::BREAKDOWNS as $by) {
            $result = $service->breakdown('2026-01-01', '2026-01-31', $by);
            $this->assertSame([], $result['rows'], $by);
            $this->assertSame(['0.00'], array_values(array_unique($result['footer'])), $by);
        }
    }

    public function test_variants_are_separate_rows(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 20)); // back to the first business day, 21:00
        $redHorse = app(ProductService::class)->create(['category_id' => Category::where('name', 'Beer')->value('id'), 'name' => 'Red Horse',
            'type' => 'drink', 'price' => '85', 'cost' => '50',
            'variants' => [['name' => 'Bucket of 6', 'price' => '480', 'cost' => '300'], ['name' => 'Tower', 'price' => '900', 'cost' => '550']]]);
        $tab = $this->openTab('T9', $this->owner);
        foreach ($redHorse->variants as $variant) {
            app(OrderService::class)->addOrder($tab, $this->owner, [['product_id' => $redHorse->id, 'variant_id' => $variant->id, 'quantity' => 1]]);
        }
        $this->pay($tab, $this->owner, 'cash', '1380');

        $rows = $this->rows('product');
        $this->assertSame(['480.00', '300.00'], [$rows['Red Horse – Bucket of 6']['sales'], $rows['Red Horse – Bucket of 6']['cogs']]);
        $this->assertSame(['900.00', '550.00'], [$rows['Red Horse – Tower']['sales'], $rows['Red Horse – Tower']['cogs']]);
    }

    public function test_archived_products_still_show(): void
    {
        app(ProductService::class)->archive($this->products['Beer']);
        Category::where('name', 'Beer')->sole()->delete();

        $this->assertSame('470.00', $this->rows('product')['Beer']['sales']);
        $this->assertSame('Beer', $this->rows('product')['Beer']['category']);
        $this->assertSame('470.00', $this->rows('category')['Beer']['sales']);
    }

    public function test_the_latest_correction_wins(): void
    {
        $cash100 = Payment::where('amount', '100')->sole(); // already corrected to GCash by the fixture
        app(PaymentCorrectionService::class)->correct($cash100, $this->owner, ['method_id' => $this->method('cash'), 'reference_no' => null, 'reason' => 'Was cash after all']);

        $rows = $this->rows('payment_method');
        $this->assertSame('638.00', $rows['GCash']['payments']);
        $this->assertSame('300.00', $rows['Cash']['payments']);
    }
}
