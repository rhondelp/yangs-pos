<?php

namespace Tests\Feature\Finance;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class ExpenseSchemaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpenseCategorySeeder::class);
        $this->user = User::factory()->create();
    }

    private function expense(array $attributes = []): Expense
    {
        return Expense::forceCreate([
            'expense_category_id' => ExpenseCategory::where('name', 'Electricity')->value('id'),
            'description' => 'Meralco November',
            'amount' => '4500.00',
            'expense_date' => '2026-11-20',
            'recorded_by' => $this->user->id,
            ...$attributes,
        ]);
    }

    /** Each refused statement runs in its own savepoint: one PostgreSQL error aborts the whole test transaction. */
    private function refused(callable $statement): void
    {
        $this->assertThrows(fn () => DB::transaction($statement), QueryException::class);
    }

    public function test_the_seeder_adds_the_seven_categories_once(): void
    {
        $this->seed(ExpenseCategorySeeder::class);

        $this->assertSame(['Electricity', 'Water', 'Rent', 'Employee-related', 'Maintenance', 'Transportation', 'Other'], ExpenseCategorySeeder::NAMES);
        $this->assertSame(ExpenseCategorySeeder::NAMES, ExpenseCategory::orderBy('id')->pluck('name')->all());
        $this->assertSame(7, ExpenseCategory::active()->count());
    }

    public function test_an_expense_can_be_voided_once_and_never_edited_or_deleted(): void
    {
        $expense = $this->expense();

        $this->assertThrows(fn () => $expense->forceFill(['amount' => '1.00'])->save(), LogicException::class);
        $this->assertThrows(fn () => $expense->fresh()->delete(), LogicException::class);

        $expense = $expense->fresh();
        $expense->forceFill(['voided_at' => now(), 'voided_by' => $this->user->id, 'void_reason' => 'Typed twice'])->save();
        $this->assertTrue($expense->fresh()->isVoided());

        $this->assertThrows(fn () => $expense->fresh()->forceFill(['void_reason' => 'Changed my mind'])->save(), LogicException::class);
        $this->assertSame('Typed twice', $expense->fresh()->void_reason);
    }

    public function test_the_database_refuses_edits_deletes_and_bad_rows(): void
    {
        $expense = $this->expense();

        $this->refused(fn () => DB::table('expenses')->where('id', $expense->id)->update(['amount' => '1.00']));
        $this->refused(fn () => DB::table('expenses')->where('id', $expense->id)->delete());
        $this->refused(fn () => $this->expense(['amount' => '0']));
        $this->refused(fn () => $this->expense(['voided_at' => now()]));

        DB::table('expenses')->where('id', $expense->id)->update(['voided_at' => now(), 'voided_by' => $this->user->id, 'void_reason' => 'Wrong bill', 'updated_at' => now()]);
        $this->refused(fn () => DB::table('expenses')->where('id', $expense->id)->update(['void_reason' => 'Other']));
    }
}
