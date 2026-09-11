<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Key/value app settings (site title, theme default, contact email, etc.) — §4.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): self
    {
        return static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Phase 2 (E5-T1) — `mail.password` is the one setting value that must never sit in the
     * database as plaintext. A dedicated accessor rather than a model-wide cast: `value` is a
     * plain string column shared by every other (unencrypted) setting row, so there is no
     * single column-level cast that could apply only to this one key.
     */
    public static function getEncrypted(string $key, ?string $default = null): ?string
    {
        $raw = static::get($key);

        if ($raw === null) {
            return $default;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException) {
            return $default;
        }
    }

    public static function putEncrypted(string $key, ?string $value): self
    {
        return static::put($key, $value === null ? null : Crypt::encryptString($value));
    }
}
