<?php

namespace App\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($user->can(Permission::DashboardOwner->value)) {
            return view('dashboard.owner', [
                // Values stay null until the modules that produce them exist; never placeholders.
                'stats' => [
                    ['label' => "Today's sales", 'value' => null, 'empty' => 'Shows once payments are recorded.'],
                    ['label' => "Today's expenses", 'value' => null, 'empty' => 'Shows once expenses are recorded.'],
                    ['label' => 'Estimated gross profit', 'value' => null, 'empty' => 'Needs sales and product costs.'],
                    ['label' => 'Estimated net profit', 'value' => null, 'empty' => 'Needs sales, costs and expenses.'],
                    ['label' => 'Open tabs', 'value' => null, 'empty' => 'Shows once tables are set up.'],
                    ['label' => 'Completed transactions', 'value' => null, 'empty' => 'Shows once bills are paid.'],
                ],
                'recentActivity' => $user->can(Permission::LogsView->value)
                    ? ActivityLog::query()->orderByDesc('id')->limit(8)->get()
                    : collect(),
            ]);
        }

        if ($user->can(Permission::DashboardCashier->value)) {
            return view('dashboard.cashier');
        }

        return view('dashboard.none');
    }
}
