<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ComponentTest extends TestCase
{
    public function test_status_badge_maps_known_statuses_and_falls_back(): void
    {
        $this->assertStringContainsString('Awaiting payment', Blade::render('<x-ui.status-badge status="awaiting_payment" />'));
        $this->assertStringContainsString('text-warning', Blade::render('<x-ui.status-badge status="awaiting_payment" />'));
        $this->assertStringContainsString('text-success', Blade::render('<x-ui.status-badge status="paid" />'));
        $this->assertStringContainsString('Low stock', Blade::render('<x-ui.status-badge status="low_stock" />'));
        $this->assertStringContainsString('text-danger', Blade::render('<x-ui.status-badge status="negative_stock" />'));

        $unknown = Blade::render('<x-ui.status-badge status="on_fire" />');
        $this->assertStringContainsString('On Fire', $unknown);
        $this->assertStringContainsString('text-muted', $unknown);
    }

    public function test_stat_card_without_a_value_shows_an_empty_state_and_no_number(): void
    {
        $html = Blade::render('<x-ui.stat-card label="Today\'s sales" empty="No sales recorded today." />');

        $this->assertStringContainsString("Today's sales", html_entity_decode($html, ENT_QUOTES));
        $this->assertStringContainsString('No sales recorded today.', $html);
        $this->assertDoesNotMatchRegularExpression('/\d/', html_entity_decode(strip_tags($html), ENT_QUOTES));

        $this->assertStringContainsString('₱1,250.00', Blade::render('<x-ui.stat-card label="Sales" value="₱1,250.00" />'));
    }

    public function test_button_renders_link_or_button_with_touch_size(): void
    {
        $button = Blade::render('<x-ui.button>Save</x-ui.button>');
        $this->assertStringContainsString('<button', $button);
        $this->assertStringContainsString('type="button"', $button);
        $this->assertStringContainsString('min-h-touch', $button);

        $link = Blade::render('<x-ui.button href="/users" variant="secondary">Users</x-ui.button>');
        $this->assertStringContainsString('<a', $link);
        $this->assertStringContainsString('href="/users"', $link);
        $this->assertStringNotContainsString('type=', $link);
    }

    public function test_input_shows_label_error_and_old_value(): void
    {
        $session = $this->app['session.store'];
        $session->flashInput(['username' => 'ana']);
        request()->setLaravelSession($session);
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['username' => ['That username is taken.']])));

        $html = Blade::render('<x-ui.input name="username" label="Username" required />');

        $this->assertStringContainsString('<label for="username"', $html);
        $this->assertStringContainsString('value="ana"', $html);
        $this->assertStringContainsString('That username is taken.', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="username-error"', $html);

        $password = Blade::render('<x-ui.input name="password" type="password" label="Password" value="secret" />');
        $this->assertStringNotContainsString('secret', $password);
    }

    public function test_confirm_dialog_posts_with_csrf_and_method(): void
    {
        $this->app['session.store']->start();
        request()->setLaravelSession($this->app['session.store']);

        $html = Blade::render('<x-ui.confirm-dialog id="deactivate-ana" title="Deactivate Ana?" action="/users/5" method="PUT" confirm="Deactivate">She will be signed out.</x-ui.confirm-dialog>');

        $this->assertStringContainsString('<dialog id="deactivate-ana"', $html);
        $this->assertStringContainsString('action="/users/5"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
        $this->assertStringContainsString('Deactivate', $html);
        $this->assertStringContainsString('She will be signed out.', $html);
    }
}
