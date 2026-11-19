<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Services\Settings\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private SettingService $settings) {}

    public function edit(): View
    {
        return view('settings.edit', ['settings' => $this->settings->all()]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->settings->set($request->settings());

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
