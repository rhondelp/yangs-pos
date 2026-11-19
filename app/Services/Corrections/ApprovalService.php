<?php

namespace App\Services\Corrections;

use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The optional supervisor PIN for voids, refunds and corrections (setting require_approval_pin).
 * Call approve() before the action's transaction, so a failed attempt's log entry is kept.
 */
class ApprovalService
{
    private const MAX_ATTEMPTS = 5; // per approver per minute

    public function __construct(private SettingService $settings, private ActivityLogger $logger) {}

    /**
     * @param  array{approver_username?: mixed, approver_pin?: mixed}  $input
     * @param  string  $action  what is being approved, for the failure log (void, refund, correction)
     * @return ?User the approver, or null when the setting is off
     *
     * @throws ValidationException on approver_pin
     */
    public function approve(array $input, string $action): ?User
    {
        if (! $this->settings->get('require_approval_pin')) {
            return null;
        }

        $username = mb_strtolower(trim((string) ($input['approver_username'] ?? '')));
        $pin = (string) ($input['approver_pin'] ?? '');
        if ($username === '' || $pin === '') {
            $this->reject('A supervisor must approve this: enter their username and PIN.');
        }

        $key = "approval:{$username}";
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->reject('Too many PIN attempts. Try again in '.RateLimiter::availableIn($key).' seconds.');
        }

        $approver = User::where('username', $username)->where('is_active', true)->first();
        if ($approver === null || $approver->approval_pin === null || ! Hash::check($pin, $approver->approval_pin) || ! $approver->can('approvals.grant')) {
            RateLimiter::hit($key, 60);
            $this->logger->log('approval.failed', null, [], ['approver' => $username, 'action' => $action]);
            $this->reject('The approver username or PIN is wrong.');
        }

        RateLimiter::clear($key);

        return $approver;
    }

    public function setPin(User $user, string $pin): void
    {
        DB::transaction(function () use ($user, $pin) {
            $user->forceFill(['approval_pin' => Hash::make($pin)])->save();
            $this->logger->log('user.approval_pin_set', $user);
        });
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['approver_pin' => $message]);
    }
}
