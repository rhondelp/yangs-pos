<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveCategoryRequest;
use App\Http\Resources\V1\CategoryResource;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categories) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::withCount('products')->orderBy('sort_order')->orderBy('name')->get());
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->loadCount('products'));
    }

    public function store(SaveCategoryRequest $request): JsonResponse
    {
        return (new CategoryResource($this->categories->create($request->categoryData())))->response()->setStatusCode(201);
    }

    public function update(SaveCategoryRequest $request, Category $category): CategoryResource
    {
        return new CategoryResource($this->categories->update($category, $request->categoryData()));
    }

    /** Archives; refused with 422 while the category has active products. */
    public function destroy(Category $category): CategoryResource
    {
        $this->categories->archive($category);

        return new CategoryResource($category);
    }

    public function restore(Category $category): CategoryResource
    {
        $this->categories->restore($category);

        return new CategoryResource($category);
    }
}
