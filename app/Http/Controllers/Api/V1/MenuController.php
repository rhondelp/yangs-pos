<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MenuCategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// What the POS may sell right now: active categories with available, non-archived products and options.
class MenuController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->whereHas('products', fn ($q) => $q->where('is_available', true))
            ->with(['products' => fn (HasMany $q) => $q->sellable()->with('variants')->orderBy('name')])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return MenuCategoryResource::collection($categories);
    }
}
