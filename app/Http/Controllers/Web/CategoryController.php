<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveCategoryRequest;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categories) {}

    public function index(): View
    {
        return view('categories.index', [
            'categories' => Category::withTrashed()->withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        $category = $this->categories->create($request->categoryData());

        return redirect()->route('categories.index')->with('status', "{$category->name} added.");
    }

    public function edit(Category $category): View
    {
        return view('categories.edit', ['category' => $category]);
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        $category = $this->categories->update($category, $request->categoryData());

        return redirect()->route('categories.index')->with('status', "{$category->name} saved.");
    }

    public function archive(Category $category): RedirectResponse
    {
        $this->categories->archive($category); // ValidationException redirects back with the reason

        return redirect()->route('categories.index')->with('status', "{$category->name} archived.");
    }

    public function restore(Category $category): RedirectResponse
    {
        $this->categories->restore($category);

        return redirect()->route('categories.index')->with('status', "{$category->name} restored.");
    }
}
