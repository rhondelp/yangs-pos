<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tables\SaveTableRequest;
use App\Models\DiningTable;
use App\Services\Tables\DiningTableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Owner setup of tables. The cashier floor is FloorController.
class TableController extends Controller
{
    public function __construct(private DiningTableService $tables) {}

    public function index(): View
    {
        return view('tables.index', ['tables' => DiningTable::query()->floorOrder()->get()]);
    }

    public function create(): View
    {
        return view('tables.create', ['table' => new DiningTable]);
    }

    public function store(SaveTableRequest $request): RedirectResponse
    {
        $table = $this->tables->create($request->tableData());

        return redirect()->route('tables.index')->with('status', "Table {$table->label} added.");
    }

    public function edit(DiningTable $table): View
    {
        return view('tables.edit', ['table' => $table]);
    }

    public function update(SaveTableRequest $request, DiningTable $table): RedirectResponse
    {
        $table = $this->tables->update($table, $request->tableData());

        return redirect()->route('tables.index')->with('status', "Table {$table->label} saved.");
    }

    public function active(Request $request, DiningTable $table): RedirectResponse
    {
        $request->validate(['is_active' => ['required', 'boolean']]);
        $table = $this->tables->setActive($table, $request->boolean('is_active'));

        return back()->with('status', $table->is_active ? "Table {$table->label} is back in use." : "Table {$table->label} is out of use and hidden from the floor.");
    }
}
