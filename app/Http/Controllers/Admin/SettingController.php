<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): View
    {
        return view('admin.settings.edit', ['values' => $this->settings->all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'restaurant_name' => ['required', 'string', 'max:120'],
            'restaurant_address' => ['nullable', 'string', 'max:255'],
            'restaurant_phone' => ['nullable', 'string', 'max:40'],
            'currency_symbol' => ['required', 'string', 'max:8'],
            'tax_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'software_credit' => ['nullable', 'string', 'max:255'],
        ]);

        // Existing open orders keep the tax they were calculated with until
        // their next change; checkout always recalculates before charging.
        $this->settings->set($validated);

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
