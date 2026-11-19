<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\SavePaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Services\Billing\PaymentMethodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function __construct(private PaymentMethodService $methods) {}

    public function index(): View
    {
        return view('payment-methods.index', ['methods' => PaymentMethod::orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(SavePaymentMethodRequest $request): RedirectResponse
    {
        $method = $this->methods->create($request->methodData());

        return redirect()->route('payment-methods.index')->with('status', "{$method->name} added.");
    }

    public function edit(PaymentMethod $paymentMethod): View
    {
        return view('payment-methods.edit', ['method' => $paymentMethod]);
    }

    public function update(SavePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $method = $this->methods->update($paymentMethod, $request->methodData()); // ValidationException redirects back

        return redirect()->route('payment-methods.index')->with('status', "{$method->name} saved.");
    }
}
