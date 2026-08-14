<?php

use App\Services\SettingsService;

if (! function_exists('settings')) {
    /** The shared settings service, or a single setting value. */
    function settings(?string $key = null, ?string $default = null): SettingsService|string|null
    {
        $settings = app(SettingsService::class);

        return $key === null ? $settings : $settings->get($key, $default);
    }
}

if (! function_exists('money')) {
    /** Format an amount with the restaurant's currency symbol. */
    function money(float|string|null $amount): string
    {
        return app(SettingsService::class)->formatMoney($amount);
    }
}
