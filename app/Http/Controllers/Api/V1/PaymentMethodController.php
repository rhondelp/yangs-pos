<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentMethodController extends Controller
{
    /** The methods a cashier can take payment with right now. */
    public function __invoke(): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection(PaymentMethod::active()->get());
    }
}
