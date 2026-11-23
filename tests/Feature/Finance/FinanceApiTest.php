<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\BuildsFinanceFixture;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use BuildsFinanceFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildFinanceFixture();
        $this->actingAs($this->owner, 'sanctum');
    }

    public function test_summary_and_breakdown_over_the_api(): void
    {
        $range = ['from' => '2026-11-20', 'to' => '2026-11-21'];

        $this->getJson(route('api.v1.finance.summary', $range))->assertOk()
            ->assertJsonPath('data.net_profit', '-6102.00')->assertJsonPath('data.inventory_purchases', '1000.00')
            ->assertJsonPath('data.from', '2026-11-20')->assertJsonPath('data.to', '2026-11-21');
        $this->getJson(route('api.v1.finance.breakdown', [...$range, 'by' => 'product']))->assertOk()
            ->assertJsonPath('data.by', 'product')->assertJsonPath('data.footer.net_sales', '888.00')
            ->assertJsonPath('data.rows.0.name', 'Beer')->assertJsonPath('data.rows.0.margin', '57.45');
        $this->getJson(route('api.v1.finance.breakdown', ['to' => '2026-11-21']))->assertOk() // from = first of the month, by = day
            ->assertJsonPath('data.from', '2026-11-01')->assertJsonPath('data.by', 'day')->assertJsonCount(2, 'data.rows');
    }

    public function test_filters_are_validated(): void
    {
        $this->getJson(route('api.v1.finance.summary', ['from' => '2026-11-21', 'to' => '2026-11-20']))->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson(route('api.v1.finance.summary', ['from' => '2020-01-01', 'to' => '2026-01-02']))->assertUnprocessable()
            ->assertJsonValidationErrors(['to' => 'Choose a range of 5 years or less.']);
        $this->getJson(route('api.v1.finance.breakdown', ['by' => 'foo']))->assertUnprocessable()
            ->assertJsonValidationErrors(['by' => 'Choose day, week, month, year, product, category, cashier or payment method.']);
        $this->getJson(route('api.v1.finance.summary', ['from' => '2026-12-15']))->assertUnprocessable() // today is 2026-11-21
            ->assertJsonValidationErrors(['from' => 'The start date cannot be after the end date.']);
        $this->getJson(route('api.v1.finance.summary', ['from' => '2019-01-01']))->assertUnprocessable()
            ->assertJsonValidationErrors(['from' => 'Choose a range of 5 years or less.']);
    }

    public function test_cashiers_cannot_see_finance(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Cashier'), 'sanctum');

        $this->getJson(route('api.v1.finance.summary'))->assertForbidden();
        $this->getJson(route('api.v1.finance.breakdown'))->assertForbidden();
    }
}
