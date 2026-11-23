<?php

namespace Tests\Feature\Finance;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Finance\ExpenseService;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseWebTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ExpenseCategorySeeder::class]);
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-22 20:00', 'Asia/Manila'));
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($this->owner);
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

    public function test_the_list_shows_both_totals_and_filters(): void
    {
        $item = app(InventoryItemService::class)->create(['name' => 'Red Horse', 'base_unit' => 'bottle']);
        app(InventoryService::class)->receive($this->owner, ['received_at' => '2026-11-10T15:00:00+08:00',
            'lines' => [['inventory_item_id' => $item->id, 'quantity' => '24', 'line_cost' => '720']]]);
        $this->expense(['amount' => '1250.50']);
        $this->expense(['description' => 'Landlord', 'amount' => '15000', 'expense_category_id' => $this->category('Rent')]);
        $voided = $this->expense(['description' => 'Typed twice', 'amount' => '999']);
        app(ExpenseService::class)->void($voided, $this->owner, 'Duplicate');

        $this->get(route('expenses.index'))->assertOk()
            ->assertSee(['Expenses', '₱16,250.50', 'Inventory purchases', '₱720.00', 'Stock-in, not an expense', 'Landlord'])
            ->assertDontSee('Typed twice');
        $this->get(route('expenses.index', ['category_id' => $this->category('Rent')]))->assertOk()
            ->assertSee(['₱15,000.00', '₱720.00'])->assertDontSee('Meralco November');
        $this->get(route('expenses.index', ['voided' => 1]))->assertSee(['Typed twice', 'Voided', 'line-through'], false)->assertSee('₱16,250.50');
        $this->get(route('expenses.index', ['from' => '2026-11-21', 'to' => '2026-11-20']))->assertSessionHasErrors('to');
    }

    public function test_record_form_records_and_redirects_to_the_expense(): void
    {
        $this->get(route('expenses.create'))->assertOk()->assertSee('multipart/form-data', false)->assertSee('image/*,application/pdf', false)
            ->assertSee('value="2026-11-22"', false);

        $response = $this->post(route('expenses.store'), ['expense_category_id' => $this->category('Water'), 'description' => 'Maynilad',
            'amount' => '850.75', 'expense_date' => '2026-11-21', 'attachment' => UploadedFile::fake()->image('maynilad.png')]);

        $expense = Expense::sole();
        $response->assertRedirect(route('expenses.show', $expense));
        $this->assertSame(['850.75', 'maynilad.png'], [$expense->amount, $expense->attachment_name]);
        $this->get(route('expenses.show', $expense))->assertOk()->assertSee(['Maynilad', '₱850.75', route('expenses.attachment', $expense)]);

        $this->post(route('expenses.store'), ['expense_category_id' => $this->category('Water'), 'description' => '', 'amount' => '5', 'expense_date' => '2026-11-21'])
            ->assertSessionHasErrors('description');
        $this->post(route('expenses.store'), ['expense_category_id' => $this->category('Water'), 'description' => 'Maynilad', 'amount' => '0', 'expense_date' => '2026-11-21'])
            ->assertSessionHasErrors(['amount' => 'The amount must be more than ₱0.00.']);
        $this->assertSame(1, Expense::count());
    }

    public function test_record_again_prefills_from_a_voided_expense(): void
    {
        $live = $this->expense(['description' => 'Still live']);
        $voided = $this->expense(['description' => 'Wrong amount', 'amount' => '120.50', 'expense_category_id' => $this->category('Transportation')]);
        app(ExpenseService::class)->void($voided, $this->owner, 'Typo');

        $this->get(route('expenses.show', $voided))->assertSee(['Typo', route('expenses.create', ['from' => $voided->id])]);
        $this->get(route('expenses.create', ['from' => $voided->id]))->assertOk()
            ->assertSee(['value="Wrong amount"', 'value="120.50"', 'value="2026-11-20"'], false)
            ->assertSee('<option value="'.$this->category('Transportation').'" selected', false);
        $this->get(route('expenses.create', ['from' => $live->id]))->assertOk()->assertDontSee('value="Still live"', false);
    }

    public function test_void_dialog_voids_with_reason(): void
    {
        $expense = $this->expense();

        $this->post(route('expenses.void', $expense), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('expenses.void', $expense), ['reason' => 'Paid by the landlord'])->assertRedirect(route('expenses.show', $expense));
        $this->assertSame('Paid by the landlord', $expense->fresh()->void_reason);
        $this->get(route('expenses.show', $expense))->assertSee(['Voided', 'Paid by the landlord']);
    }

    public function test_receipt_access_is_permission_gated(): void
    {
        $expense = $this->expense(file: UploadedFile::fake()->image('bill.jpg'));
        $plain = $this->expense();

        $this->get(route('expenses.attachment', $expense))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->cashier)->get(route('expenses.attachment', $expense))->assertForbidden();

        $viewer = User::factory()->create()->assignRole(Role::create(['name' => 'Viewer'])->givePermissionTo('expenses.view'));
        $this->actingAs($viewer)->get(route('expenses.attachment', $expense))->assertOk();
        app(ExpenseService::class)->void($expense, $this->owner, 'Wrong bill');
        $this->actingAs($viewer)->get(route('expenses.attachment', $expense))->assertOk(); // still evidence after a void
        $this->actingAs($viewer)->get(route('expenses.attachment', $plain))->assertNotFound();
        $this->actingAs($viewer)->post(route('expenses.void', $plain), ['reason' => 'x'])->assertForbidden();
        $this->assertStringNotContainsString('public', Storage::disk('local')->path($expense->attachment_path));
    }

    public function test_pages_need_their_permissions(): void
    {
        $expense = $this->expense();
        $this->actingAs($this->cashier);

        $this->get(route('expenses.index'))->assertForbidden();
        $this->get(route('expenses.show', $expense))->assertForbidden();
        $this->get(route('expenses.create'))->assertForbidden();
        $this->post(route('expenses.store'), [])->assertForbidden();
        $this->post(route('expenses.void', $expense), ['reason' => 'x'])->assertForbidden();
    }

    public function test_navigation_shows_expenses_to_viewers_only(): void
    {
        $this->get(route('dashboard'))->assertSee(route('expenses.index'));
        $this->actingAs($this->cashier)->get(route('dashboard'))->assertDontSee(route('expenses.index'));
    }
}
