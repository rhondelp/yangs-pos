<?php

namespace Tests\Feature\Finance;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Finance\ExpenseService;
use Carbon\CarbonImmutable;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ExpenseCategorySeeder::class]);
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-22 20:00', 'Asia/Manila'));
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner, 'sanctum');
    }

    private function category(string $name): int
    {
        return ExpenseCategory::where('name', $name)->value('id');
    }

    private function expense(array $input = [], ?UploadedFile $file = null): Expense
    {
        return app(ExpenseService::class)->record($this->owner, ['expense_category_id' => $this->category('Electricity'),
            'description' => 'Meralco November', 'amount' => '4500', 'expense_date' => '2026-11-20', ...$input], $file);
    }

    public function test_the_list_filters_and_reports_both_totals(): void
    {
        $this->expense(['amount' => '1000']);
        $this->expense(['amount' => '250.25', 'expense_category_id' => $this->category('Rent')]);
        app(ExpenseService::class)->void($this->expense(['amount' => '999']), $this->owner, 'Duplicate');

        $this->getJson(route('api.v1.expenses.index'))->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.totals.expenses', '1250.25')
            ->assertJsonPath('meta.totals.inventory_purchases', '0.00');
        $this->getJson(route('api.v1.expenses.index', ['category_id' => $this->category('Rent'), 'voided' => 1]))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.category', 'Rent')->assertJsonPath('meta.totals.expenses', '250.25');
        $this->getJson(route('api.v1.expenses.index', ['voided' => 1]))->assertJsonCount(3, 'data')->assertJsonPath('meta.totals.expenses', '1250.25');
        $this->getJson(route('api.v1.expenses.index', ['from' => '2026-11-21', 'to' => '2026-11-20', 'category_id' => 999999]))
            ->assertUnprocessable()->assertJsonValidationErrors(['to', 'category_id']);
    }

    public function test_record_with_a_receipt_show_and_void(): void
    {
        $id = $this->post(route('api.v1.expenses.store'), ['expense_category_id' => $this->category('Maintenance'), 'description' => 'Aircon cleaning',
            'amount' => '1800', 'expense_date' => '2026-11-21', 'attachment' => UploadedFile::fake()->image('aircon.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '1800.00')
            ->assertJsonPath('data.has_attachment', true)
            ->assertJsonPath('data.attachment_name', 'aircon.jpg')
            ->assertJsonPath('data.voided', false)
            ->json('data.id');

        $this->getJson(route('api.v1.expenses.show', $id))->assertOk()->assertJsonPath('data.description', 'Aircon cleaning')->assertJsonMissingPath('data.attachment_path');
        $this->postJson(route('api.v1.expenses.void', $id), ['reason' => 'Paid by the landlord'])->assertOk()
            ->assertJsonPath('data.voided', true)->assertJsonPath('data.void_reason', 'Paid by the landlord');
        $this->postJson(route('api.v1.expenses.void', $id), ['reason' => 'Again'])->assertUnprocessable()
            ->assertJsonPath('errors.reason.0', 'This expense is already voided.');
        $this->postJson(route('api.v1.expenses.store'), ['expense_category_id' => $this->category('Maintenance'), 'description' => 'x', 'amount' => '0', 'expense_date' => '2026-11-21'])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_a_json_number_amount_is_accepted(): void
    {
        $this->postJson(route('api.v1.expenses.store'), ['expense_category_id' => $this->category('Rent'), 'description' => 'Rent', 'amount' => 18000.5, 'expense_date' => '2026-11-21'])
            ->assertCreated()->assertJsonPath('data.amount', '18000.50');
    }

    public function test_the_receipt_needs_expenses_view(): void
    {
        $expense = $this->expense(file: UploadedFile::fake()->image('bill.jpg'));

        $this->get(route('api.v1.expenses.attachment', $expense))->assertOk();
        $this->actingAs(User::factory()->create()->assignRole('Cashier'), 'sanctum');
        $this->getJson(route('api.v1.expenses.attachment', $expense))->assertForbidden();
        $this->getJson(route('api.v1.expenses.index'))->assertForbidden();
        $this->postJson(route('api.v1.expenses.void', $expense), ['reason' => 'x'])->assertForbidden();
    }

    public function test_an_end_date_alone_starts_the_range_at_its_month(): void
    {
        $this->expense(['amount' => '700', 'expense_date' => '2026-10-15']);

        $this->getJson(route('api.v1.expenses.index', ['to' => '2026-10-31']))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.totals.expenses', '700.00');
    }

    public function test_the_categories_list(): void
    {
        $this->getJson(route('api.v1.expense-categories.index'))->assertOk()
            ->assertJsonCount(7, 'data')->assertJsonPath('data.0.name', 'Electricity')->assertJsonPath('data.0.is_active', true);
    }
}
