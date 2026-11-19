<?php

namespace Tests\Feature\Billing;

use App\Models\ActivityLog;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Billing\PaymentMethodService;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
    }

    public function test_the_owner_adds_a_non_cash_method_and_it_is_logged(): void
    {
        $this->actingAs($this->owner)
            ->post('/payment-methods', ['name' => ' Maya ', 'requires_reference' => '1', 'sort_order' => 3])
            ->assertRedirect('/payment-methods');

        $maya = PaymentMethod::where('name', 'Maya')->sole();
        $this->assertSame('maya', $maya->code);
        $this->assertFalse($maya->is_cash);
        $this->assertTrue($maya->requires_reference);
        $this->assertTrue($maya->is_active);
        $this->assertEquals(['name' => 'Maya', 'requires_reference' => true, 'is_active' => true, 'sort_order' => 3], // jsonb reorders keys
            ActivityLog::where('action', 'payment_method.created')->sole()->new_values);
    }

    public function test_a_name_already_used_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post('/payment-methods', ['name' => 'gcash'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_code_clash_after_slugging_gets_a_suffix(): void
    {
        $this->actingAs($this->owner);
        $service = app(PaymentMethodService::class);
        $service->create(['name' => 'Bank transfer']);

        $this->assertSame('bank-transfer-2', $service->create(['name' => 'Bank Transfer!'])->code);
    }

    public function test_updating_logs_only_the_changed_fields(): void
    {
        $gcash = PaymentMethod::where('code', 'gcash')->sole();

        $this->actingAs($this->owner)
            ->put("/payment-methods/{$gcash->id}", ['name' => 'GCash', 'requires_reference' => '1', 'is_active' => '0', 'sort_order' => 2])
            ->assertRedirect('/payment-methods');

        $this->assertFalse($gcash->fresh()->is_active);
        $log = ActivityLog::where('action', 'payment_method.updated')->sole();
        $this->assertSame(['is_active' => true], $log->old_values);
        $this->assertSame(['is_active' => false], $log->new_values);
    }

    public function test_the_last_active_method_cannot_be_switched_off(): void
    {
        PaymentMethod::where('code', 'gcash')->update(['is_active' => false]);
        $cash = PaymentMethod::where('code', 'cash')->sole();

        $this->actingAs($this->owner)
            ->put("/payment-methods/{$cash->id}", ['name' => 'Cash', 'is_active' => '0'])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($cash->fresh()->is_active);
    }

    public function test_the_page_needs_settings_manage(): void
    {
        $this->actingAs($this->cashier)->get('/payment-methods')->assertForbidden();
        $this->actingAs($this->owner)->get('/payment-methods')->assertOk()->assertSee('GCash');
    }

    public function test_the_api_lists_active_methods_in_order_for_cashiers(): void
    {
        PaymentMethod::forceCreate(['code' => 'old-card', 'name' => 'Old card', 'is_active' => false]);

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/v1/payment-methods')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', ['id' => PaymentMethod::where('code', 'cash')->value('id'), 'code' => 'cash', 'name' => 'Cash', 'is_cash' => true, 'requires_reference' => false])
            ->assertJsonPath('data.1.code', 'gcash');
    }
}
