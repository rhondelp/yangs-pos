<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ReceiptResource;
use App\Models\TableSession;
use App\Services\Billing\ReceiptService;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    /** 422 for a tab that is not closed. A client fetches this to print, so it is logged like the print page. */
    public function show(Request $request, TableSession $session, ReceiptService $receipts): ReceiptResource
    {
        $data = $receipts->data($session);

        return new ReceiptResource([...$data, 'reprint' => $receipts->recordPrint($session, $request->user())]);
    }
}
