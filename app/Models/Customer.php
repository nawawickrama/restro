<?php

namespace App\Models;

use App\Enums\CustomerSource;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Someone the restaurant has served.
 *
 * The mobile number is the identity — plenty of customers never give a name,
 * and two people called "Nimal" are not the same person. Orders keep their own
 * copy of whatever was typed at the time, so this record can be renamed or
 * removed without touching a single receipt.
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = ['name', 'phone', 'phone_digits', 'source', 'note'];

    protected function casts(): array
    {
        return ['source' => CustomerSource::class];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Digits only, so a number typed with spaces still matches one without. */
    public static function normalisePhone(?string $phone): string
    {
        return preg_replace('/\D/', '', (string) $phone) ?? '';
    }

    /** Find whoever holds this number, however it was written. */
    public static function findByPhone(?string $phone): ?self
    {
        $digits = self::normalisePhone($phone);

        return $digits === '' ? null : static::query()->where('phone_digits', $digits)->first();
    }

    /** What to call them in a list when no name was ever given. */
    public function displayName(): string
    {
        return filled($this->name) ? $this->name : $this->phone;
    }

    /** @param Builder<$this> $query */
    public function scopeOfSource(Builder $query, CustomerSource $source): void
    {
        $query->where('source', $source);
    }
}
