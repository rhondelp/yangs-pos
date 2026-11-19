<?php

namespace Tests\Feature\Corrections;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Corrections\ApprovalService;
use App\Services\Settings\SettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    private ApprovalService $approvals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create(['username' => 'boss'])->assignRole('Owner');
        $this->cashier = User::factory()->create(['username' => 'ana'])->assignRole('Cashier');
        $this->actingAs($this->cashier);
        $this->approvals = app(ApprovalService::class);
        $this->approvals->setPin($this->owner, '4321');
        RateLimiter::clear('approval:boss');
    }

    private function turnOn(): void
    {
        $this->actingAs($this->owner);
        app(SettingService::class)->set(['require_approval_pin' => true]);
        $this->actingAs($this->cashier);
    }

    private function assertRefused(array $input, string $message = 'The approver username or PIN is wrong.'): void
    {
        try {
            $this->approvals->approve($input, 'refund');
            $this->fail('expected a refusal');
        } catch (ValidationException $e) {
            $this->assertSame($message, $e->errors()['approver_pin'][0]);
        }
    }

    public function test_with_the_setting_off_no_approval_is_needed(): void
    {
        $this->assertNull($this->approvals->approve([], 'refund'));
    }

    public function test_with_the_setting_on_a_supervisor_pin_is_required_and_checked(): void
    {
        $this->turnOn();

        $this->assertRefused([], 'A supervisor must approve this: enter their username and PIN.');
        $this->assertRefused(['approver_username' => 'boss', 'approver_pin' => '0000']);
        $this->assertEquals(['approver' => 'boss', 'action' => 'refund'], // jsonb reorders keys
            ActivityLog::where('action', 'approval.failed')->latest('id')->first()->new_values);

        $this->assertTrue($this->owner->is($this->approvals->approve(['approver_username' => ' BOSS ', 'approver_pin' => '4321'], 'refund')));
    }

    public function test_only_active_users_with_approvals_grant_can_approve(): void
    {
        $this->turnOn();
        $this->approvals->setPin($this->cashier, '1111');
        $this->assertRefused(['approver_username' => 'ana', 'approver_pin' => '1111']);

        $this->owner->forceFill(['is_active' => false])->save();
        $this->assertRefused(['approver_username' => 'boss', 'approver_pin' => '4321']);
    }

    public function test_the_sixth_failed_attempt_in_a_minute_is_blocked_even_with_the_right_pin(): void
    {
        $this->turnOn();
        foreach (range(1, 5) as $i) {
            $this->assertRefused(['approver_username' => 'boss', 'approver_pin' => '9999']);
        }

        try {
            $this->approvals->approve(['approver_username' => 'boss', 'approver_pin' => '4321'], 'refund');
            $this->fail('expected the throttle');
        } catch (ValidationException $e) {
            $this->assertStringStartsWith('Too many PIN attempts.', $e->errors()['approver_pin'][0]);
        }
    }

    public function test_the_pin_is_stored_hashed_and_never_logged(): void
    {
        $pin = $this->owner->fresh()->approval_pin;
        $this->assertNotSame('4321', $pin);
        $this->assertTrue(Hash::check('4321', $pin));
        $this->assertNull(ActivityLog::where('action', 'user.approval_pin_set')->latest('id')->first()->new_values);
    }

    public function test_the_profile_page_sets_a_pin_for_approvers_only(): void
    {
        $this->actingAs($this->owner)->get('/profile')->assertSee('Approval PIN');
        $this->actingAs($this->owner)->put('/profile/approval-pin', ['approval_pin' => '12a4', 'approval_pin_confirmation' => '12a4'])->assertSessionHasErrorsIn('approvalPin', 'approval_pin');
        $this->actingAs($this->owner)->put('/profile/approval-pin', ['approval_pin' => '246810', 'approval_pin_confirmation' => '246810'])->assertRedirect('/profile');
        $this->assertTrue(Hash::check('246810', $this->owner->fresh()->approval_pin));

        $this->actingAs($this->cashier)->get('/profile')->assertDontSee('Approval PIN');
        $this->actingAs($this->cashier)->put('/profile/approval-pin', ['approval_pin' => '1234', 'approval_pin_confirmation' => '1234'])->assertForbidden();
    }

    public function test_new_permissions_exist_and_are_not_cashier_defaults(): void
    {
        $cashier = Role::findByName('Cashier');
        foreach (['orders.void', 'payments.refund', 'corrections.create'] as $permission) {
            $this->assertFalse($cashier->hasPermissionTo($permission), $permission);
            $this->assertTrue($this->owner->can($permission), $permission);
        }
    }
}
