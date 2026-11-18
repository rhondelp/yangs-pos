<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use App\Services\Audit\ActivityLogger;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * All product writes. Price or cost changes always write product_price_histories and a
 * product.price_changed activity log in the same transaction (CLAUDE.md: order history
 * snapshots prices, so history here is the record of what the menu said and when).
 */
class ProductService
{
    private const FIELDS = ['category_id', 'name', 'description', 'type', 'is_available', 'track_inventory'];

    private const MONEY = ['price', 'cost'];

    private const DISK = 'public';

    public function __construct(private ActivityLogger $logger) {}

    /**
     * @param array{category_id: int, name: string, description?: ?string, type: string, price: string, cost?: ?string,
     *              is_available?: bool, track_inventory?: bool, variants?: list<array{name: string, price: string, cost?: ?string}>} $data
     */
    public function create(array $data, ?UploadedFile $image = null): Product
    {
        $path = $image?->store('products', self::DISK);

        try {
            return DB::transaction(function () use ($data, $path) {
                $product = Product::create([
                    ...Arr::only($data, [...self::FIELDS, ...self::MONEY]),
                    'image_path' => $path,
                ]);
                $this->recordPrices($product, ['price' => null, 'cost' => null]);

                foreach (array_values($data['variants'] ?? []) as $i => $row) {
                    $this->createVariant($product, $row, $i);
                }

                $this->logger->log('product.created', $product, new: $this->auditValues($product));

                return $product->load('variants');
            });
        } catch (Throwable $e) {
            $path && Storage::disk(self::DISK)->delete($path);

            throw $e;
        }
    }

    /**
     * Partial update: only keys present in $data change. When 'variants' is present it is the full list;
     * rows with an id update that option, rows without one add an option, missing options are archived.
     */
    public function update(Product $product, array $data, ?UploadedFile $image = null, bool $removeImage = false): Product
    {
        $newPath = $image?->store('products', self::DISK);

        try {
            [$product, $oldPath] = DB::transaction(function () use ($product, $data, $newPath, $removeImage) {
                $product = Product::whereKey($product->getKey())->lockForUpdate()->firstOrFail();
                $oldPrices = $product->only(self::MONEY);
                $oldPath = null;

                $product->fill(Arr::only($data, self::FIELDS));
                if ($newPath !== null || $removeImage) {
                    $oldPath = $product->image_path;
                    $product->image_path = $newPath;
                }
                $this->fillMoney($product, $data);

                $changed = array_diff_key($product->getDirty(), array_flip(self::MONEY));
                $before = array_intersect_key($product->getRawOriginal(), $changed);
                $product->save();

                if ($changed) {
                    $this->logger->log('product.updated', $product, $before, $changed);
                }
                $this->recordPrices($product, $oldPrices, logChange: true);

                if (array_key_exists('variants', $data)) {
                    $this->syncVariants($product, array_values($data['variants'] ?? []));
                }

                return [$product->load('variants'), $oldPath];
            });
        } catch (Throwable $e) {
            $newPath && Storage::disk(self::DISK)->delete($newPath);

            throw $e;
        }

        // Only after commit: a rolled-back update must keep its image.
        if ($oldPath !== null && $oldPath !== $product->image_path) {
            Storage::disk(self::DISK)->delete($oldPath);
        }

        return $product;
    }

    public function setAvailability(Product $product, bool $available): Product
    {
        return $this->update($product, ['is_available' => $available]);
    }

    public function archive(Product $product): void
    {
        DB::transaction(fn () => $product->delete()); // logs product.archived; its options leave the menu with it
    }

    public function restore(Product $product): void
    {
        DB::transaction(fn () => $product->restore()); // logs product.restored
    }

    /** @param list<array{id?: ?int, name: string, price: string, cost?: ?string}> $rows */
    private function syncVariants(Product $product, array $rows): void
    {
        $existing = $product->variants()->lockForUpdate()->get()->keyBy('id');
        $keep = array_filter(array_column($rows, 'id'));

        foreach ($keep as $i => $id) {
            if (! $existing->has($id)) {
                throw ValidationException::withMessages(["variants.{$i}.id" => 'This option does not belong to this product.']);
            }
        }

        // Archive first so a removed name can be reused in the same save.
        foreach ($existing->except($keep) as $gone) {
            $gone->delete(); // logs product_variant.archived
        }

        foreach ($rows as $i => $row) {
            if (empty($row['id'])) {
                $this->createVariant($product, $row, $i);

                continue;
            }

            $variant = $existing->get($row['id']);
            $oldPrices = $variant->only(self::MONEY);
            $variant->fill(['name' => $row['name'], 'sort_order' => $i]);
            $this->fillMoney($variant, $row);

            $changed = array_diff_key($variant->getDirty(), array_flip([...self::MONEY, 'sort_order']));
            $before = array_intersect_key($variant->getRawOriginal(), $changed);
            $variant->save();

            if ($changed) {
                $this->logger->log('product_variant.updated', $variant, $before, $changed);
            }
            $this->recordPrices($variant, $oldPrices, logChange: true);
        }
    }

    private function createVariant(Product $product, array $row, int $position): ProductVariant
    {
        $variant = $product->variants()->create([
            'name' => $row['name'],
            'price' => $row['price'],
            'cost' => $row['cost'] ?? null,
            'sort_order' => $position,
        ]);
        $this->recordPrices($variant, ['price' => null, 'cost' => null]);
        $this->logger->log('product_variant.created', $variant, new: $this->auditValues($variant));

        return $variant;
    }

    /** Sets price/cost only when the amount really changed, so "85" vs "85.00" is not a change. */
    private function fillMoney(Model $model, array $data): void
    {
        foreach (self::MONEY as $field) {
            if (array_key_exists($field, $data) && ! Money::equals($this->amount($model->{$field}), $this->amount($data[$field]))) {
                $model->{$field} = $data[$field];
            }
        }
    }

    /** One history row per changed field; optionally one product.price_changed log with old and new. */
    private function recordPrices(Product|ProductVariant $model, array $oldPrices, bool $logChange = false): void
    {
        $old = $new = [];

        foreach (self::MONEY as $field) {
            $before = $this->amount($oldPrices[$field] ?? null);
            $after = $this->amount($model->{$field});

            if (Money::equals($before, $after)) {
                continue;
            }

            ProductPriceHistory::create([
                'product_id' => $model instanceof Product ? $model->id : $model->product_id,
                'product_variant_id' => $model instanceof ProductVariant ? $model->id : null,
                'field' => $field,
                'old_value' => $before,
                'new_value' => $after,
                'changed_by' => auth()->id(),
            ]);
            $old[$field] = $before;
            $new[$field] = $after;
        }

        if ($logChange && $new) {
            $this->logger->log('product.price_changed', $model, $old, $new);
        }
    }

    private function amount(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : Money::round((string) $value);
    }

    private function auditValues(Model $model): array
    {
        $values = Arr::except($model->attributesToArray(), ['id', 'created_at', 'updated_at', 'deleted_at', 'product_id', 'sort_order']);

        foreach (self::MONEY as $field) {
            $values[$field] = $this->amount($model->{$field});
        }

        return $values;
    }
}
