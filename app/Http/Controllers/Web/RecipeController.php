<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveRecipeRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\RecipeService;
use Illuminate\Http\RedirectResponse;

class RecipeController extends Controller
{
    public function update(SaveRecipeRequest $request, Product $product, RecipeService $recipes): RedirectResponse
    {
        $variantId = $request->validated('product_variant_id');
        $variant = $variantId ? ProductVariant::findOrFail($variantId) : null;
        $recipes->save($product, $variant, $request->components()); // ValidationException goes back to the card

        return redirect()->route('products.edit', $product)->with('status', 'Recipe for '.($variant?->name ?? $product->name).' saved.');
    }
}
