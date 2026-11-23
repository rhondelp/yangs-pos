<?php

namespace Tests\Feature\Finance;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Finance\ExpenseService;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private ExpenseService $expenses;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ExpenseCategorySeeder::class]);
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-22 20:00', 'Asia/Manila'));
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->expenses = app(ExpenseService::class);
    }

    private function category(string $name): int
    {
        return ExpenseCategory::where('name', $name)->value('id');
    }

    private function record(array $input = [], ?UploadedFile $file = null, ?User $by = null): Expense
    {
        return $this->expenses->record($by ?? $this->owner, [
            'expense_category_id' => $this->category('Electricity'),
            'description' => 'Meralco November',
            'amount' => '4500.00',
            'expense_date' => '2026-11-20',
            ...$input,
        ], $file);
    }

    private function filters(array $filters = []): array
    {
        return ['from' => '2026-11-01', 'to' => '2026-11-30', 'category_id' => null, 'voided' => false, ...$filters];
    }

    private function assertRefused(callable $do, string $key): void
    {
        try {
            $do();
            $this->fail("expected a refusal on {$key}");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), json_encode($e->errors()));
        }
    }

    public function test_record_saves_logs_and_stores_the_receipt_privately(): void
    {
        $expense = $this->record(file: UploadedFile::fake()->image('meralco bill.jpg'));

        $this->assertSame(['4500.00', 'Meralco November', '2026-11-20', $this->owner->id], [$expense->amount, $expense->description, $expense->expense_date->toDateString(), $expense->recorded_by]);
        $this->assertStringStartsWith('expenses/2026/11/', $expense->attachment_path);
        $this->assertSame('meralco bill.jpg', $expense->attachment_name);
        Storage::disk('local')->assertExists($expense->attachment_path);
        $this->assertEquals( // jsonb does not keep key order
            ['category' => 'Electricity', 'description' => 'Meralco November', 'amount' => '4500.00', 'expense_date' => '2026-11-20', 'attachment' => true],
            ActivityLog::where('action', 'expense.recorded')->sole()->new_values,
        );
    }

    public function test_record_validation(): void
    {
        foreach (['0', '-1', '1.234', '10000000000', 'abc'] as $amount) {
            $this->assertRefused(fn () => $this->record(['amount' => $amount]), 'amount');
        }
        $this->assertRefused(fn () => $this->record(['description' => '  ']), 'description');
        $this->assertRefused(fn () => $this->record(['description' => str_repeat('x', 201)]), 'description');
        $this->assertRefused(fn () => $this->record(['expense_category_id' => 999999]), 'expense_category_id');
        ExpenseCategory::where('name', 'Water')->update(['is_active' => false]);
        $this->assertRefused(fn () => $this->record(['expense_category_id' => $this->category('Water')]), 'expense_category_id');
        $this->assertRefused(fn () => $this->record(['expense_date' => '2026-11-23']), 'expense_date');
        $this->assertRefused(fn () => $this->record(['expense_date' => '20/11/2026']), 'expense_date');
        $this->assertRefused(fn () => $this->record(file: UploadedFile::fake()->create('notes.txt', 1, 'text/plain')), 'attachment');
        $this->assertRefused(fn () => $this->record(file: UploadedFile::fake()->image('huge.jpg')->size(5121)), 'attachment');
        // An executable renamed to .pdf: the content decides, not the name.
        $this->assertRefused(fn () => $this->record(file: $this->upload('evil.pdf', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF".str_repeat("\x00", 64))), 'attachment');

        $this->assertSame(0, Expense::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_pdf_receipt_is_accepted(): void
    {
        $expense = $this->record(file: $this->upload('rent.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"));

        Storage::disk('local')->assertExists($expense->attachment_path);
    }

    /** A real temp-file upload: its MIME type comes from the content (finfo), as in production. Fakes report it from the name. */
    private function upload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'exp');
        file_put_contents($path, $content);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_an_upload_the_server_refused_names_the_limit(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'exp');
        $this->beforeApplicationDestroyed(fn () => @unlink($path));
        $tooBig = new UploadedFile($path, 'camera.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        try {
            $this->record(file: $tooBig);
            $this->fail('expected a refusal');
        } catch (ValidationException $e) {
            $this->assertSame('The receipt could not be uploaded. It may be larger than the server allows; keep it under 5 MB.', $e->errors()['attachment'][0]);
        }
    }

    public function test_a_receipt_that_could_not_be_saved_stops_the_expense(): void
    {
        $file = \Mockery::mock(UploadedFile::fake()->image('bill.jpg'))->makePartial();
        $file->shouldReceive('store')->andReturn(false); // what the `local` disk returns when the write fails (throw => false)

        $this->assertRefused(fn () => $this->record(file: $file), 'attachment');
        $this->assertSame(0, Expense::count());
    }

    public function test_the_file_name_must_also_have_a_receipt_extension(): void
    {
        ob_start();
        imagejpeg(imagecreatetruecolor(20, 20));
        $jpeg = ob_get_clean();

        $this->assertRefused(fn () => $this->record(file: $this->upload('receipt.html', $jpeg)), 'attachment');
        $this->assertNotNull($this->record(file: $this->upload('receipt.JPG', $jpeg))->attachment_path);
    }

    public function test_an_expense_dated_today_before_the_cutoff_is_accepted(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-22 01:00', 'Asia/Manila')); // business day is still the 21st

        $this->assertSame('2026-11-21', $this->record(['expense_date' => '2026-11-21'])->expense_date->toDateString());
        $this->assertRefused(fn () => $this->record(['expense_date' => '2026-11-22']), 'expense_date');
    }

    public function test_a_failed_save_leaves_no_file(): void
    {
        Expense::creating(fn () => throw new RuntimeException('database down'));

        $this->assertThrows(fn () => $this->record(file: UploadedFile::fake()->image('bill.jpg')), RuntimeException::class);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_void_once_with_reason_and_logged(): void
    {
        $expense = $this->record();

        $this->assertRefused(fn () => $this->expenses->void($expense, $this->owner, ' '), 'reason');
        $voided = $this->expenses->void($expense, $this->owner, 'Recorded twice');

        $this->assertTrue($voided->isVoided());
        $this->assertSame([$this->owner->id, 'Recorded twice'], [$voided->voided_by, $voided->void_reason]);
        $log = ActivityLog::where('action', 'expense.voided')->sole();
        $this->assertEquals(['reason' => 'Recorded twice', 'amount' => '4500.00'], $log->new_values);
        try {
            $this->expenses->void($expense, $this->owner, 'Again');
            $this->fail('a second void must be refused');
        } catch (ValidationException $e) {
            $this->assertSame('This expense is already voided.', $e->errors()['reason'][0]);
        }
    }

    public function test_recording_and_voiding_need_expenses_manage(): void
    {
        $cashier = User::factory()->create()->assignRole('Cashier');
        $expense = $this->record();

        $this->assertThrows(fn () => $this->record(by: $cashier), AuthorizationException::class);
        $this->assertThrows(fn () => $this->expenses->void($expense, $cashier, 'No'), AuthorizationException::class);
    }

    public function test_voided_expenses_are_excluded_from_totals(): void
    {
        $this->record(['amount' => '1250.50']);
        $this->record(['amount' => '300']);
        $this->expenses->void($this->record(['amount' => '999']), $this->owner, 'Wrong amount');

        $this->assertSame('1550.50', $this->expenses->totals($this->filters())['expenses']);
        $this->assertSame('1550.50', $this->expenses->totals($this->filters(['voided' => true]))['expenses']); // shown, never counted
        $this->assertCount(2, $this->expenses->list($this->filters())->items());
        $this->assertCount(3, $this->expenses->list($this->filters(['voided' => true]))->items());
    }

    public function test_totals_follow_dates_and_category(): void
    {
        $this->record(['amount' => '100', 'expense_date' => '2026-10-31']);
        $this->record(['amount' => '200', 'expense_date' => '2026-11-01']);
        $this->record(['amount' => '50', 'expense_date' => '2026-11-15', 'expense_category_id' => $this->category('Rent')]);

        $this->assertSame('250.00', $this->expenses->totals($this->filters())['expenses']);
        $this->assertSame('50.00', $this->expenses->totals($this->filters(['category_id' => $this->category('Rent')]))['expenses']);
        $this->assertSame('0.00', $this->expenses->totals($this->filters(['from' => '2026-12-01', 'to' => '2026-12-31']))['expenses']);
    }

    public function test_inventory_purchases_sum_stock_in_by_business_date_and_ignore_category(): void
    {
        $item = app(InventoryItemService::class)->create(['name' => 'Red Horse', 'base_unit' => 'bottle']);
        $receive = fn (string $at, string $cost) => app(InventoryService::class)->receive($this->owner, ['received_at' => $at,
            'lines' => [['inventory_item_id' => $item->id, 'quantity' => '24', 'line_cost' => $cost]]]);
        $receive('2026-11-10T15:00:00+08:00', '720');
        $receive('2026-11-01T03:00:00+08:00', '1000'); // before 06:00: business day Oct 31, outside the range
        $receive('2026-11-20T15:00:00+08:00', '280.50');
        $this->record(['amount' => '400']);

        $totals = $this->expenses->totals($this->filters());
        $this->assertSame(['expenses' => '400.00', 'inventory_purchases' => '1000.50'], $totals);
        $this->assertSame('1000.50', $this->expenses->totals($this->filters(['category_id' => $this->category('Rent')]))['inventory_purchases']);
    }
}
