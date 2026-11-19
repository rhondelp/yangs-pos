<?php

namespace App\Http\Controllers\Web;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\TableSession;
use App\Services\Billing\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    /** Stand-alone print page; each view is logged as a print or reprint. */
    public function show(Request $request, TableSession $session, ReceiptService $receipts): View
    {
        abort_unless($session->status === SessionStatus::Closed, 404);

        $receipt = $receipts->data($session);
        $reprint = $receipts->recordPrint($session, $request->user());

        return view('receipts.show', [
            'receipt' => $receipt,
            'reprint' => $reprint,
            'paper' => $request->query('paper') === '58' ? 58 : 80,
        ]);
    }
}
