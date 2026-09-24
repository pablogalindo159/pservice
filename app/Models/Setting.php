<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::$cache ??= static::query()->pluck('value', 'key')->all();

        return self::$cache[$key] ?? $default;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }
        self::$cache = null;
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
