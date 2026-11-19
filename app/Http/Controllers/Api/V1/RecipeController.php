<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveRecipeRequest;
use App\Http\Resources\V1\RecipeComponentResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecipeComponent;
use App\Services\Inventory\RecipeService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecipeController extends Controller
{
    /** Every recipe of the product: product_variant_id null is the product sold without an option. */
    public function show(Product $product): AnonymousResourceCollection
    {
        return RecipeComponentResource::collection(RecipeComponent::where('product_id', $product->id)->with('item')->orderBy('id')->get());
    }

    public function update(SaveRecipeRequest $request, Product $product, RecipeService $recipes): AnonymousResourceCollection
    {
        $variantId = $request->validated('product_variant_id');
        $recipes->save($product, $variantId ? ProductVariant::findOrFail($variantId) : null, $request->components());

        return $this->show($product);
    }
}
