<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

/**
 * The restaurant's own details, as they appear on the receipt, the customer
 * display and the top of every screen.
 *
 * Only fills in what is missing. A restaurant that has edited its address on
 * the settings screen keeps that edit, however many times this is re-run.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $values = array_merge(SettingsService::DEFAULTS, [
            'restaurant_name' => 'K&D Foods & Catering',
            'restaurant_address' => 'No 1351/1, Biyagama Road, Kelaniya 11870',
            'restaurant_phone' => '077 291 5469',
            'receipt_footer' => 'Every Meal. A Signature Experience.',
        ]);

        foreach ($values as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        app(SettingsService::class)->flush();
    }
}
