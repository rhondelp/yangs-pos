<?php

namespace App\Services\Billing;

use App\Enums\SessionStatus;
use App\Models\ActivityLog;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Orders\RunningBill;
use App\Services\Settings\SettingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** What a closed tab's receipt shows, and the print / reprint audit trail. */
class ReceiptService
{
    public function __construct(private SettingService $settings, private RunningBill $bill, private ActivityLogger $logger) {}

    /**
     * Totals come from the figures frozen when the tab closed.
     *
     * @throws ValidationException when the tab is not closed
     */
    public function data(TableSession $session): array
    {
        if ($session->status !== SessionStatus::Closed) {
            throw ValidationException::withMessages(['session' => 'A receipt is ready once the bill is fully paid.']);
        }

        $session->loadMissing('table', 'closedBy');
        $bill = $this->bill->calculate($session);

        return [
            'business' => [
                'name' => $this->settings->get('business_name'),
                'address' => $this->settings->get('business_address'),
                'tin' => $this->settings->get('business_tin'),
                'footer' => $this->settings->get('receipt_footer'),
            ],
            'receipt_number' => $session->receipt_number,
            'table' => $session->table->label,
            'cashier' => $session->closedBy?->name,
            'opened_at' => $session->opened_at->toIso8601String(),
            'closed_at' => $session->closed_at->toIso8601String(),
            // Net of voids: the bill already took voided units off each line.
            'items' => array_map(fn (array $item) => [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'],
                'line_total' => $item['line_total'],
            ], $bill['items']),
            'gross' => $session->gross_total,
            'discount' => $session->discount_total, // item + bill discounts, for API clients
            'item_discounts' => $bill['discounts'], // already shown on each line
            'bill_discount' => $bill['bill_discount'],
            'discount_reason' => $session->bill_discount_reason,
            'total' => $session->net_total,
            'paid' => $bill['paid'],
            'change' => array_reduce($bill['payments'], fn (string $sum, array $p) => Money::add($sum, $p['change_given']), '0.00'),
            'payments' => $bill['payments'],
        ];
    }

    /** Logs receipt.printed the first time, receipt.reprinted after. Returns true for a reprint. */
    public function recordPrint(TableSession $session, User $by): bool
    {
        return DB::transaction(function () use ($session, $by) {
            TableSession::whereKey($session->getKey())->lockForUpdate()->first(); // two tablets: only one first print

            $reprint = ActivityLog::where('action', 'receipt.printed')
                ->where('subject_type', $session->getMorphClass())
                ->where('subject_id', $session->getKey())
                ->exists();
            $this->logger->log($reprint ? 'receipt.reprinted' : 'receipt.printed', $session, [], ['receipt_number' => $session->receipt_number], $by);

            return $reprint;
        });
    }
}
