<?php

namespace Tests\Feature\Pos;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Catalog\CategoryService;
use App\Services\Catalog\ProductService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TableSessionService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    private DiningTable $t1;

    private Product $redHorse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');

        $this->actingAs($this->owner);
        $beer = Category::create(['name' => 'Beer', 'sort_order' => 10]);
        $food = Category::create(['name' => 'Food', 'sort_order' => 20]);
        $products = app(ProductService::class);
        $this->redHorse = $products->create(['category_id' => $beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50',
            'variants' => [['name' => 'Bucket of 6', 'price' => '480', 'cost' => '315']]]);
        $products->setAvailability($products->create(['category_id' => $food->id, 'name' => 'Sisig', 'type' => 'food', 'price' => '220']), false);
        $products->archive($products->create(['category_id' => $beer->id, 'name' => 'Old brew', 'type' => 'drink', 'price' => '60']));

        $gone = Category::create(['name' => 'Gone', 'sort_order' => 30]);
        $orphan = $products->create(['category_id' => $gone->id, 'name' => 'Orphan', 'type' => 'drink', 'price' => '10']);
        $products->archive($orphan);
        app(CategoryService::class)->archive($gone->fresh());
        $products->restore(Product::withTrashed()->find($orphan->id));

        $this->t1 = app(DiningTableService::class)->create(['label' => 'T1']);
    }

    private function openTab(DiningTable $table): TableSession
    {
        return app(TableSessionService::class)->open($table, $this->cashier);
    }

    public function test_the_pos_screen_bootstraps_the_menu_and_the_tab(): void
    {
        $session = $this->openTab($this->t1);
        app(OrderService::class)->addOrder($session, $this->cashier, [['product_id' => $this->redHorse->id, 'quantity' => 2]]);

        $response = $this->actingAs($this->cashier)->get("/pos/sessions/{$session->id}")
            ->assertOk()
            ->assertViewIs('pos.show')
            ->assertSee('data-layout="pos"', false);

        $boot = $response->viewData('bootstrap');
        $this->assertSame(['Beer', 'Food'], array_column($boot['menu'], 'name'));
        $this->assertSame(['Red Horse'], array_column($boot['menu'][0]['products'], 'name'));
        $this->assertSame([['id' => $this->redHorse->variants[0]->id, 'name' => 'Bucket of 6', 'price' => '480.00']], $boot['menu'][0]['products'][0]['variants']);
        $this->assertTrue($boot['menu'][0]['products'][0]['available']);
        $this->assertSame('Sisig', $boot['menu'][1]['products'][0]['name']);
        $this->assertFalse($boot['menu'][1]['products'][0]['available']);

        $this->assertSame($session->id, $boot['session']['id']);
        $this->assertSame('T1', $boot['session']['table']['label']);
        $this->assertSame('170.00', $boot['session']['bill']['total']);
        $this->assertCount(1, $boot['session']['orders']);

        $json = json_encode($boot);
        $this->assertStringNotContainsString('cost', $json);
        $this->assertStringNotContainsString('Old brew', $json);
        $this->assertStringNotContainsString('Orphan', $json);
    }

    public function test_the_switcher_lists_every_open_tab(): void
    {
        $session = $this->openTab($this->t1);
        $other = $this->openTab(app(DiningTableService::class)->create(['label' => 'T2']));

        $boot = $this->actingAs($this->cashier)->get("/pos/sessions/{$session->id}")->viewData('bootstrap');

        $this->assertSame(['T1', 'T2'], array_column($boot['tabs'], 'label'));
        $this->assertSame(route('pos.show', $other), $boot['tabs'][1]['url']);
    }

    public function test_a_closed_tab_sends_the_cashier_back_to_the_floor(): void
    {
        $session = $this->openTab($this->t1);
        app(TableSessionService::class)->cancel($session, $this->owner, 'Opened by mistake');

        $this->actingAs($this->cashier)->get("/pos/sessions/{$session->id}")
            ->assertRedirect(route('floor'))
            ->assertSessionHas('error');
    }

    public function test_the_pos_screen_needs_the_ordering_permission(): void
    {
        $session = $this->openTab($this->t1);
        Role::findByName('Bartender')->givePermissionTo('tables.view');

        $this->actingAs(User::factory()->create()->assignRole('Bartender'))->get("/pos/sessions/{$session->id}")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get("/pos/sessions/{$session->id}")->assertRedirect('/login');
    }

    public function test_the_floor_links_busy_tables_and_offers_open_tab_on_free_ones(): void
    {
        $session = $this->openTab($this->t1);
        app(DiningTableService::class)->create(['label' => 'T2']);

        $this->actingAs($this->cashier)->get('/floor')
            ->assertOk()
            ->assertSee('href="'.route('pos.show', $session).'"', false)
            ->assertSee('Open tab');

        Role::findByName('Bartender')->givePermissionTo('tables.view');
        $this->actingAs(User::factory()->create()->assignRole('Bartender'))->get('/floor')
            ->assertOk()
            ->assertDontSee('Open tab');
    }

    public function test_the_browser_session_works_on_the_api_until_the_user_is_deactivated(): void
    {
        $this->actingAs($this->cashier)
            ->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/floor'])
            ->getJson('/api/v1/sessions')
            ->assertOk();

        $this->cashier->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/sessions')->assertUnauthorized();
    }
}
