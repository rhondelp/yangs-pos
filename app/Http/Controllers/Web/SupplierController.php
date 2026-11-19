<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveSupplierRequest;
use App\Models\Supplier;
use App\Services\Inventory\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(private SupplierService $suppliers) {}

    public function index(): View
    {
        return view('suppliers.index', ['suppliers' => Supplier::withTrashed()->withCount('receipts')->orderBy('name')->get()]);
    }

    public function store(SaveSupplierRequest $request): RedirectResponse
    {
        $supplier = $this->suppliers->create($request->supplierData());

        return redirect()->route('suppliers.index')->with('status', "{$supplier->name} added.");
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', ['supplier' => $supplier]);
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier = $this->suppliers->update($supplier, $request->supplierData());

        return redirect()->route('suppliers.index')->with('status', "{$supplier->name} saved.");
    }

    public function archive(Supplier $supplier): RedirectResponse
    {
        $this->suppliers->archive($supplier);

        return redirect()->route('suppliers.index')->with('status', "{$supplier->name} archived.");
    }

    public function restore(Supplier $supplier): RedirectResponse
    {
        $this->suppliers->restore($supplier);

        return redirect()->route('suppliers.index')->with('status', "{$supplier->name} restored.");
    }
}
