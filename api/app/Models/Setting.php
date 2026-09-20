<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /** Valeurs par défaut des paramètres de la plateforme. */
    public const DEFAULTS = [
        'files' => [
            'max_size_mb' => 50,
            'allowed_extensions' => [
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                'jpg', 'jpeg', 'png', 'gif', 'webp',
                'mp3', 'wav', 'm4a', 'ogg',
                'mp4', 'webm', 'mov',
                'zip', 'txt', 'csv',
            ],
            'retention_days' => 0, // 0 = conservation illimitée
        ],
        'reminders' => [
            'days_before_due' => [3, 1, 0],
        ],
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting:{$key}", function () use ($key, $default) {
            $row = static::find($key);

            return $row ? $row->value : ($default ?? self::DEFAULTS[$key] ?? null);
        });
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }
}
