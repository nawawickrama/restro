<?php

namespace App\Models;

use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Model;

/**
 * Key/value store for the handful of restaurant-wide settings the MVP needs.
 * Read through {@see SettingsService}, which caches the whole set.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];
}
