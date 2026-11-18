<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductFilterRequest;
use App\Http\Requests\Catalog\SaveProductRequest;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    private const WITH = ['category', 'variants'];

    public function __construct(private ProductService $products) {}

    public function index(ProductFilterRequest $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            Product::query()->with(self::WITH)->filter($request->filters())->orderBy('name')
                ->paginate($request->integer('per_page', 50))->withQueryString()
        );
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(self::WITH));
    }

    public function store(SaveProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->productData(), $request->file('image'));

        return (new ProductResource($product->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(SaveProductRequest $request, Product $product): ProductResource
    {
        $product = $this->products->update($product, $request->productData(), $request->file('image'), $request->boolean('remove_image'));

        return new ProductResource($product->load(self::WITH));
    }

    /** Archives; nothing is hard-deleted. */
    public function destroy(Product $product): ProductResource
    {
        $this->products->archive($product);

        return new ProductResource($product->load(self::WITH));
    }

    public function restore(Product $product): ProductResource
    {
        $this->products->restore($product);

        return new ProductResource($product->load(self::WITH));
    }
}
