<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Restaurant-wide settings, read constantly (every receipt, every price on
 * screen) and written rarely, so the whole set is cached as one array.
 */
class SettingsService
{
    private const CACHE_KEY = 'restro.settings';

    /** @var array<string, string> */
    public const DEFAULTS = [
        'restaurant_name' => 'Restro',
        'restaurant_address' => '',
        'restaurant_phone' => '',
        'currency_symbol' => 'Rs.',
        'tax_percentage' => '0',
        'receipt_footer' => 'Thank you. Please come again!',
        'software_credit' => 'Software By - Nawawickrama (0779389533)',
    ];

    /** @return array<string, string> */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return array_merge(self::DEFAULTS, Setting::query()->pluck('value', 'key')->all());
        });
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    /** @param array<string, string|null> $values */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function restaurantName(): string
    {
        return (string) $this->get('restaurant_name');
    }

    public function currencySymbol(): string
    {
        return (string) $this->get('currency_symbol');
    }

    /** Percentage applied to (subtotal - discount) at checkout. 0 disables tax. */
    public function taxPercentage(): float
    {
        return round((float) $this->get('tax_percentage'), 2);
    }

    /** "Rs. 2,500.00" — the one place money becomes a string for humans. */
    public function formatMoney(float|string|null $amount): string
    {
        return $this->currencySymbol().' '.number_format((float) $amount, 2);
    }
}
