<?php

namespace App\Services\Corrections;

use App\Models\Payment;
use App\Models\PaymentCorrection;
use App\Models\PaymentMethod;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Fixes a payment's method or reference with a new record; the payment row stays as recorded. */
class PaymentCorrectionService
{
    public function __construct(private ActivityLogger $logger, private ApprovalService $approvals) {}

    /**
     * @param  array{method_id: mixed, reference_no?: ?string, reason: mixed, approver_username?: mixed, approver_pin?: mixed}  $input
     *
     * @throws AuthorizationException without corrections.create
     * @throws ValidationException when the method, reference, reason or approval is invalid, or nothing changes
     */
    public function correct(Payment $payment, User $by, array $input): PaymentCorrection
    {
        if (! $by->can('corrections.create')) {
            throw new AuthorizationException('You cannot correct payments.');
        }

        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            $this->reject('reason', 'Give a reason for the correction.');
        }
        if (mb_strlen($reason) > 200) {
            $this->reject('reason', 'The reason may not be longer than 200 characters.');
        }

        $methodId = $input['method_id'] ?? null;
        $method = is_numeric($methodId) ? PaymentMethod::whereKey((int) $methodId)->where('is_active', true)->first() : null;
        if ($method === null) {
            $this->reject('method_id', 'Choose an active payment method.');
        }
        if (! $method->is_cash && bccomp($payment->change_given, '0', 2) > 0) {
            $this->reject('method_id', 'Change was given, so this was a cash payment.');
        }
        $reference = $method->is_cash ? null : (trim((string) ($input['reference_no'] ?? '')) ?: null);
        if ($reference === null && $method->requires_reference) {
            $this->reject('reference_no', "Enter the {$method->name} reference number.");
        }
        if ($reference !== null && mb_strlen($reference) > 60) {
            $this->reject('reference_no', 'The reference number may not be longer than 60 characters.');
        }

        $approver = $this->approvals->approve($input, 'correction');

        return DB::transaction(function () use ($payment, $by, $method, $reference, $reason, $approver) {
            TableSession::whereKey($payment->table_session_id)->lockForUpdate()->firstOrFail(); // one correction at a time per tab
            $current = Payment::with('method', 'corrections.newMethod')->findOrFail($payment->getKey());
            [$oldMethod, $oldReference] = $current->effective();

            if ($oldMethod->is($method) && $oldReference === $reference) {
                $this->reject('method_id', 'Nothing to correct: that is already the method and reference.');
            }

            $correction = PaymentCorrection::create([
                'payment_id' => $payment->id,
                'table_session_id' => $payment->table_session_id,
                'old_payment_method_id' => $oldMethod->id,
                'new_payment_method_id' => $method->id,
                'old_reference_no' => $oldReference,
                'new_reference_no' => $reference,
                'reason' => $reason,
                'corrected_by' => $by->id,
                'approved_by' => $approver?->id,
            ]);
            $this->logger->log('payment.corrected', $payment,
                ['method' => $oldMethod->name, 'reference_no' => $oldReference],
                ['method' => $method->name, 'reference_no' => $reference, 'reason' => $reason], $by, $approver);

            return $correction;
        });
    }

    private function reject(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
