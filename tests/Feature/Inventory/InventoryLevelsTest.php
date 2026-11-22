<?php

namespace Tests\Feature\Inventory;

use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Catalog\CategoryService;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryReportService;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryLevelsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private InventoryItemService $items;

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->items = app(InventoryItemService::class);
        $this->inventory = app(InventoryService::class);
    }

    /** An item with $quantity in stock bought for $cost in total. */
    private function item(string $name, string $quantity = '0', string $cost = '0', string $low = '0', array $extra = []): InventoryItem
    {
        $item = $this->items->create(['name' => $name, 'base_unit' => 'bottle', 'low_stock_level' => $low, ...$extra]);
        if (bccomp($quantity, '0', 3) > 0) {
            $this->inventory->receive($this->owner, ['received_at' => now()->toIso8601String(), 'lines' => [['inventory_item_id' => $item->id, 'quantity' => $quantity, 'line_cost' => $cost]]]);
        }

        return $item->fresh();
    }

    public function test_value_is_on_hand_times_average_rounded_per_item(): void
    {
        $gin = $this->item('Gin', '3', '100'); // avg 33.333333
        $this->inventory->stockOut($gin, $this->owner, ['quantity' => '0.5', 'reason' => 'damaged']);

        $this->assertSame('2.500', $gin->fresh()->on_hand);
        $this->assertSame('83.33', $gin->fresh()->stockValue());
        $gin->forceFill(['on_hand' => '3.5'])->save(); // 3.5 × 33.333333 = 116.6666655
        $this->assertSame('116.67', $gin->fresh()->stockValue());
    }

    public function test_negative_and_zero_stock_are_worth_nothing(): void
    {
        $item = $this->item('Gin', '3', '90');
        $item->forceFill(['on_hand' => '0'])->save();
        $this->assertSame('0.00', $item->fresh()->stockValue());
        $item->forceFill(['on_hand' => '-2'])->save();
        $this->assertSame('0.00', $item->fresh()->stockValue());
    }

    public function test_status_for_thresholds(): void
    {
        $this->assertSame('low_stock', InventoryItem::statusFor('5', '5'));
        $this->assertSame('in_stock', InventoryItem::statusFor('5.001', '5'));
        $this->assertSame('out_of_stock', InventoryItem::statusFor('0', '5'));
        $this->assertSame('negative_stock', InventoryItem::statusFor('-1', '5'));
        $this->assertSame('in_stock', InventoryItem::statusFor('1', '0'));
        $this->assertSame('out_of_stock', InventoryItem::statusFor('0', '0'));
        $this->assertSame(['in_stock' => 0, 'low_stock' => 1, 'out_of_stock' => 2, 'negative_stock' => 3], InventoryItem::STATUS_RANK);
    }

    public function test_an_item_can_have_a_category_and_the_change_is_logged(): void
    {
        $beer = app(CategoryService::class)->create(['name' => 'Beer']);
        $item = $this->item('Red Horse');

        $this->put(route('inventory.update', $item), ['name' => 'Red Horse', 'base_unit' => 'bottle', 'category_id' => $beer->id])
            ->assertRedirect(route('inventory.show', $item))->assertSessionHasNoErrors();

        $this->assertSame($beer->id, $item->fresh()->category_id);
        $this->assertSame('Beer', $item->fresh()->category->name);
        $log = ActivityLog::where('action', 'inventory_item.updated')->latest('id')->first();
        $this->assertSame(['category_id' => null], $log->old_values);
        $this->assertSame(['category_id' => $beer->id], $log->new_values);
        $this->get(route('inventory.show', $item))->assertOk()->assertSee('Beer');
    }

    public function test_an_archived_category_is_kept_but_not_newly_chosen(): void
    {
        $categories = app(CategoryService::class);
        $old = $categories->create(['name' => 'Old stock']);
        $kept = $this->item('Kept', extra: ['category_id' => $old->id]);
        $other = $this->item('Other');
        $categories->archive($old);

        $this->put(route('inventory.update', $kept), ['name' => 'Kept', 'base_unit' => 'bottle', 'category_id' => $old->id])->assertSessionHasNoErrors();
        $this->get(route('inventory.show', $kept))->assertSee('Old stock (archived)');

        $this->put(route('inventory.update', $other), ['name' => 'Other', 'base_unit' => 'bottle', 'category_id' => $old->id])
            ->assertSessionHasErrors(['category_id' => 'Choose a category that is in use.']);
        $this->put(route('inventory.update', $other), ['name' => 'Other', 'base_unit' => 'bottle', 'category_id' => 999999])
            ->assertSessionHasErrors('category_id');
        $this->assertNull($other->fresh()->category_id);
    }

    private function levels(array $filters = []): array
    {
        return app(InventoryReportService::class)->levels([...['search' => null, 'category_id' => null, 'status' => null, 'archived' => false], ...$filters]);
    }

    /** In stock (20 > 5), at the level (5), out (0) and below zero (-2). */
    private function statuses(): array
    {
        $in = $this->item('In', '20', '200', '5');
        $low = $this->item('Low', '5', '50', '5');
        $out = $this->item('Out', '0', '0', '5');
        $neg = $this->item('Neg', '3', '30', '5');
        $neg->forceFill(['on_hand' => '-2'])->save();

        return [$in, $low, $out, $neg];
    }

    public function test_the_total_is_the_sum_of_rounded_rows(): void
    {
        $a = $this->item('A', '3', '100'); // avg 33.333333
        $a->forceFill(['on_hand' => '3.5'])->save(); // 116.6666655 → 116.67
        $b = $this->item('B', '1', '0.01');
        $b->forceFill(['on_hand' => '0.5', 'avg_unit_cost' => '0.013'])->save(); // 0.0065 → 0.01

        $this->assertSame(['total_value' => '116.68', 'item_count' => 2, 'low' => 0, 'out' => 0, 'negative' => 0], $this->levels()['summary']);
    }

    public function test_archived_items_count_only_when_asked(): void
    {
        $this->item('Kept', '2', '100');
        $gone = $this->item('Gone', '1', '40');
        $this->items->archive($gone);

        $this->assertSame(['Kept'], $this->levels()['items']->pluck('name')->all());
        $this->assertSame('100.00', $this->levels()['summary']['total_value']);
        $this->assertSame(['Gone', 'Kept'], $this->levels(['archived' => true])['items']->pluck('name')->all());
        $this->assertSame('140.00', $this->levels(['archived' => true])['summary']['total_value']);
    }

    public function test_status_filters_follow_the_badge_rules(): void
    {
        $this->statuses();
        $names = fn (string $status) => $this->levels(['status' => $status])['items']->pluck('name')->all();

        $this->assertSame(['Low'], $names('low'));
        $this->assertSame(['Out'], $names('out'));
        $this->assertSame(['Neg'], $names('negative'));
        $this->assertSame(['Low', 'Neg', 'Out'], $names('attention'));
    }

    public function test_a_negative_item_counts_as_negative_but_adds_nothing(): void
    {
        $this->statuses();
        $summary = $this->levels()['summary'];

        $this->assertSame(['total_value' => '250.00', 'item_count' => 4, 'low' => 1, 'out' => 1, 'negative' => 1], $summary);
        $this->assertSame('0.00', $this->levels(['status' => 'negative'])['summary']['total_value']);
    }

    public function test_search_matches_name_or_sku_literally(): void
    {
        $this->item('Gin 50%');
        $this->item('Gin 500');
        $this->item('Tanduay', extra: ['sku' => 'TND_750']);
        $this->item('Tanduay Dark', extra: ['sku' => 'TNDX750']);
        $names = fn (string $search) => $this->levels(['search' => $search])['items']->pluck('name')->all();

        $this->assertSame(['Gin 50%'], $names('50%'));
        $this->assertSame(['Tanduay'], $names('tnd_'));
        $this->assertSame(['Gin 50%', 'Gin 500'], $names('GIN'));
    }

    public function test_category_filter(): void
    {
        $beer = app(CategoryService::class)->create(['name' => 'Beer']);
        $this->item('Red Horse', extra: ['category_id' => $beer->id]);
        $this->item('Ice');

        $this->assertSame(['Red Horse'], $this->levels(['category_id' => $beer->id])['items']->pluck('name')->all());
    }

    public function test_invalid_filters_are_refused(): void
    {
        $this->getJson(route('api.v1.inventory.valuation', ['status' => 'bad', 'category_id' => 999999]))
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'category_id']);
        $this->get(route('inventory.index', ['status' => 'bad']))->assertSessionHasErrors('status');
    }

    public function test_the_stock_levels_page_shows_summary_and_filters(): void
    {
        $this->statuses();

        $this->get(route('inventory.index', ['status' => 'attention']))->assertOk()
            ->assertSee('₱50.00')
            ->assertSee(['Low', 'Out', 'Neg'])
            ->assertDontSee(route('inventory.show', InventoryItem::where('name', 'In')->first()));
    }

    public function test_the_valuation_api_returns_summary_and_items(): void
    {
        $this->statuses();

        $this->getJson(route('api.v1.inventory.valuation', ['status' => 'attention']))->assertOk()
            ->assertJsonPath('summary.total_value', '50.00')
            ->assertJsonPath('summary.item_count', 3)
            ->assertJsonPath('data.0.name', 'Low')
            ->assertJsonPath('data.0.value', '50.00');

        $this->actingAs(User::factory()->create());
        $this->getJson(route('api.v1.inventory.valuation'))->assertForbidden();
    }

    public function test_the_items_api_accepts_search_category_and_status(): void
    {
        $beer = app(CategoryService::class)->create(['name' => 'Beer']);
        $this->item('Red Horse', '2', '60', '5', ['category_id' => $beer->id]);
        $this->item('San Mig', '20', '600', '5', ['category_id' => $beer->id]);
        $this->item('Red Wine', '2', '600', '5');

        $this->getJson(route('api.v1.inventory-items.index', ['search' => 'red', 'category_id' => $beer->id, 'status' => 'low']))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Red Horse');
    }

    public function test_a_non_numeric_category_is_refused_not_a_crash(): void
    {
        $item = $this->item('Red Horse');

        $this->putJson(route('api.v1.inventory-items.update', $item), ['category_id' => 'abc'])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
    }

    public function test_archived_items_never_need_attention(): void
    {
        $gone = $this->item('Gone', '0', '0', '5'); // out, then archived
        $this->items->archive($gone);
        $this->item('Low', '5', '50', '5');

        $levels = $this->levels(['archived' => true]);
        $this->assertSame(['item_count' => 2, 'low' => 1, 'out' => 0, 'negative' => 0], array_diff_key($levels['summary'], ['total_value' => 0]));
        $this->assertSame(['Low'], $this->levels(['archived' => true, 'status' => 'attention'])['items']->pluck('name')->all());
        $this->assertSame([], $this->levels(['archived' => true, 'status' => 'out'])['items']->pluck('name')->all());
    }

    public function test_a_failed_new_item_does_not_set_the_category_filter(): void
    {
        $beer = app(CategoryService::class)->create(['name' => 'Beer']);
        $this->item('Red Horse');

        $this->from(route('inventory.index'))->post(route('inventory.store'), ['name' => 'Red Horse', 'base_unit' => 'bottle', 'category_id' => $beer->id])
            ->assertSessionHasErrors('name');

        $html = $this->get(route('inventory.index'))->assertOk()->getContent();
        preg_match('/<select[^>]*id="filter-category".*?<\/select>/s', $html, $filter);
        $this->assertNotEmpty($filter);
        $this->assertStringNotContainsString('selected', $filter[0]);
    }

    public function test_the_items_api_shows_category_and_value(): void
    {
        $beer = app(CategoryService::class)->create(['name' => 'Beer']);
        $item = $this->item('Red Horse', '10', '300', extra: ['category_id' => $beer->id]);

        $this->getJson(route('api.v1.inventory-items.show', $item))->assertOk()
            ->assertJsonPath('data.category_id', $beer->id)
            ->assertJsonPath('data.category', 'Beer')
            ->assertJsonPath('data.value', '300.00');
    }
}
