<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(private ActivityLogger $logger) {}

    /** @param array{name: string, sort_order?: int} $data */
    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            $category = Category::create(Arr::only($data, ['name', 'sort_order']));
            $this->logger->log('category.created', $category, new: $category->only('name', 'sort_order'));

            return $category;
        });
    }

    /** @param array{name?: string, sort_order?: int} $data */
    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $category->fill(Arr::only($data, ['name', 'sort_order']));
            $new = $category->getDirty();
            $old = array_intersect_key($category->getRawOriginal(), $new);
            $category->save();

            if ($new) {
                $this->logger->log('category.updated', $category, $old, $new);
            }

            return $category;
        });
    }

    /** @throws ValidationException while the category still has active products */
    public function archive(Category $category): void
    {
        DB::transaction(function () use ($category) {
            Category::whereKey($category->getKey())->lockForUpdate()->first();
            $count = $category->products()->count();

            if ($count > 0) {
                throw ValidationException::withMessages([
                    'category' => "This category still has {$count} active ".Str::plural('product', $count).'. Move or archive them first.',
                ]);
            }

            $category->delete(); // logs category.archived
        });
    }

    public function restore(Category $category): void
    {
        DB::transaction(fn () => $category->restore()); // logs category.restored
    }
}
