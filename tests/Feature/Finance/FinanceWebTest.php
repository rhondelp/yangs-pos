<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\BuildsFinanceFixture;
use Tests\TestCase;

class FinanceWebTest extends TestCase
{
    use BuildsFinanceFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildFinanceFixture();
    }

    public function test_the_page_shows_the_statement_and_breakdown(): void
    {
        $this->get(route('finance.index', ['from' => '2026-11-20', 'to' => '2026-11-21', 'by' => 'product']))->assertOk()
            ->assertSeeInOrder(['Net sales', '₱888.00', 'Cost of goods sold', '₱300.00', 'Operating expenses', '₱6,500.00',
                'Inventory losses', '₱190.00', 'Net profit', '-₱6,102.00'])
            ->assertSee(['Inventory purchases', '₱1,000.00', 'not in profit', '1 item sold without a cost', 'Gin Tonic', '66.67%'])
            ->assertSeeInOrder(['Bill discounts', '-₱82.00', 'Refunds', '-₱50.00', 'Net sales', '₱888.00']);
    }

    public function test_every_breakdown_renders(): void
    {
        foreach (['day' => 'Fri, Nov 20, 2026', 'week' => 'Nov 16 – Nov 22, 2026', 'month' => 'November 2026', 'year' => '2026',
            'category' => 'Cocktails', 'cashier' => 'Carlo Cashier', 'payment_method' => 'GCash'] as $by => $text) {
            $this->get(route('finance.index', ['from' => '2026-11-20', 'to' => '2026-11-21', 'by' => $by]))->assertOk()->assertSee($text);
        }
        $this->get(route('finance.index', ['from' => '2026-01-01', 'to' => '2026-01-31']))->assertOk()->assertSee('No sales or costs in this period.');
    }

    public function test_cashiers_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Cashier'))->get(route('finance.index'))->assertForbidden();
    }
}
