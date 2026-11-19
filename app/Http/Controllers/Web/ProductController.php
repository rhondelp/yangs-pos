<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductFilterRequest;
use App\Http\Requests\Catalog\SaveProductRequest;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\RecipeComponent;
use App\Services\Catalog\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(ProductFilterRequest $request): View
    {
        return view('products.index', [
            'products' => Product::query()->with('category')->withCount('variants')
                ->filter($request->filters())
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->filters(),
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('products.create', $this->formData(new Product(['type' => ProductType::Drink, 'is_available' => true])));
    }

    public function store(SaveProductRequest $request): RedirectResponse
    {
        $product = $this->products->create($request->productData(), $request->file('image'));

        return redirect()->route('products.index')->with('status', "{$product->name} added to the menu.");
    }

    public function edit(Product $product): View
    {
        $product->load(['variants', 'priceHistory' => fn ($q) => $q->with(['variant', 'changedBy'])->limit(50)]);

        return view('products.edit', [
            ...$this->formData($product),
            // Recipe card: what each way of selling this product takes from stock (keyed by option id, '' = no option).
            'recipes' => RecipeComponent::where('product_id', $product->id)->with('item')->orderBy('id')->get()->groupBy(fn ($r) => (string) $r->product_variant_id),
            'inventoryItems' => InventoryItem::orderBy('name')->get(['id', 'name', 'base_unit']),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $product = $this->products->update($product, $request->productData(), $request->file('image'), $request->boolean('remove_image'));

        return redirect()->route('products.index')->with('status', "{$product->name} saved.");
    }

    public function availability(Product $product): RedirectResponse
    {
        $product = $this->products->setAvailability($product, ! $product->is_available);

        return back()->with('status', $product->is_available ? "{$product->name} is back on the menu." : "{$product->name} is hidden from the menu.");
    }

    public function archive(Product $product): RedirectResponse
    {
        $this->products->archive($product);

        return redirect()->route('products.index')->with('status', "{$product->name} archived.");
    }

    public function restore(Product $product): RedirectResponse
    {
        $this->products->restore($product);

        return redirect()->route('products.index')->with('status', "{$product->name} restored.");
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'types' => ProductType::cases(),
        ];
    }
}
