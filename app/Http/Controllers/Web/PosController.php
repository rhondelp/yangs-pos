<?php

namespace App\Http\Controllers\Web;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TableSessionResource;
use App\Models\Category;
use App\Models\TableSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The cashier's ordering screen for one tab. The page ships its menu and tab as JSON so the first paint
 * needs no request; everything after that (sending orders, refreshing the tab) goes through /api/v1.
 */
class PosController extends Controller
{
    public function show(Request $request, TableSession $session): View|RedirectResponse
    {
        $session->load('table');

        if (! $session->status->isOpen()) {
            return redirect()->route('floor')->with('error', "The tab on table {$session->table->label} is closed.");
        }

        $session->load('orders.items');

        return view('pos.show', ['bootstrap' => [
            'session' => (new TableSessionResource($session))->response($request)->getData(true)['data'],
            'menu' => $this->menu(),
            'tabs' => $this->openTabs($session),
            'urls' => [
                'session' => route('api.v1.sessions.show', $session),
                'orders' => route('api.v1.orders.store', $session),
                'floor' => route('floor'),
                'login' => route('login'),
            ],
        ]]);
    }

    /** Active categories with their non-archived products. Unavailable ones are kept, flagged, so tiles can say so. */
    private function menu(): array
    {
        return Category::query()
            ->with(['products' => fn ($q) => $q->with('variants')->orderBy('name')])
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty())
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'products' => $category->products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'image_url' => $product->imageUrl(),
                    'available' => $product->is_available,
                    'variants' => $product->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => $v->price])->all(),
                ])->all(),
            ])
            ->values()
            ->all();
    }

    private function openTabs(TableSession $current): array
    {
        return TableSession::query()
            ->whereIn('status', [SessionStatus::Open, SessionStatus::Billing])
            ->with('table')
            ->get()
            ->sortBy(fn (TableSession $s) => [$s->table->sort_order, $s->table->label])
            ->map(fn (TableSession $s) => [
                'id' => $s->id,
                'label' => $s->table->label,
                'url' => route('pos.show', $s),
                'current' => $s->is($current),
            ])
            ->values()
            ->all();
    }
}
