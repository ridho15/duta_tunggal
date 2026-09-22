<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value', 'description'];

    /**
     * Get a setting value, falling back to $default if not set.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        // Cast booleans stored as '1'/'0'/'true'/'false'
        if (in_array(strtolower((string) $setting->value), ['true', 'false', '1', '0'], true)) {
            return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
        }

        return $setting->value;
    }

    /**
     * Persist a setting value (upsert by key).
     */
    public static function set(string $key, mixed $value, string $description = ''): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value, 'description' => $description]
        );
    }

    /**
     * Whether DO approval is required before a Delivery Order can be marked as Sent.
     * Falls back to config/procurement.php → env DO_APPROVAL_REQUIRED.
     */
    public static function doApprovalRequired(): bool
    {
        return static::get(
            'do_approval_required',
            config('procurement.do_approval_required', true)
        );
    }
}
