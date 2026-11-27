<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Audit\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * SystemSetting — generic key/value store for runtime configuration overrides.
 *
 * This is the data model for System Settings (barcode/printer settings now,
 * more groups later). Defaults live in config/barcode_settings.php; a row
 * in this table overrides the config default for its key.
 *
 * Keys are dot-notation namespaced per settings group, e.g.:
 *   barcode.default_label_format
 *   barcode.show_price_on_label
 *
 * Audit: every create/update is audit-logged via the Auditable trait
 * (actions 'system_setting.create' / 'system_setting.update').
 */
class SystemSetting extends Model
{
    use Auditable, HasFactory;

    /**
     * Audit configuration.
     */
    protected string $auditPrefix = 'system_setting';

    protected string $auditCategory = 'settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'string',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Find a setting by its full key.
     */
    public function scopeForKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    /**
     * Scope: All settings within a group (e.g. group 'barcode' matches
     * 'barcode.default_printer', 'barcode.show_price_on_label', ...).
     */
    public function scopeForGroup($query, string $group)
    {
        return $query->where('key', 'LIKE', $group.'.%');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Set (upsert) a setting value.
     */
    public static function setValue(string $key, ?string $value): self
    {
        return static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Get a setting value from the database (null when not overridden).
     */
    public static function getValue(string $key): ?string
    {
        return static::query()->forKey($key)->value('value');
    }
}
