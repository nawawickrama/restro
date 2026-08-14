<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $values = array_merge(SettingsService::DEFAULTS, [
            'restaurant_name' => 'Restro Kitchen',
            'restaurant_address' => '42 Galle Road, Colombo 03',
            'restaurant_phone' => '011 234 5678',
        ]);

        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        app(SettingsService::class)->flush();
    }
}
