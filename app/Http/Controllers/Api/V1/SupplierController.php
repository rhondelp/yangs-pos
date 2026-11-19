<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveSupplierRequest;
use App\Http\Resources\V1\SupplierResource;
use App\Models\Supplier;
use App\Services\Inventory\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    public function __construct(private SupplierService $suppliers) {}

    public function index(): AnonymousResourceCollection
    {
        return SupplierResource::collection(Supplier::orderBy('name')->get());
    }

    public function store(SaveSupplierRequest $request): JsonResponse
    {
        return (new SupplierResource($this->suppliers->create($request->supplierData())))->response()->setStatusCode(201);
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        return new SupplierResource($this->suppliers->update($supplier, $request->supplierData()));
    }

    public function destroy(Supplier $supplier): SupplierResource
    {
        $this->suppliers->archive($supplier);

        return new SupplierResource($supplier);
    }

    public function restore(Supplier $supplier): SupplierResource
    {
        $this->suppliers->restore($supplier);

        return new SupplierResource($supplier);
    }
}
