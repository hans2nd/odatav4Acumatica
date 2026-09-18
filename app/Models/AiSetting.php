<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Set a configuration value, optionally encrypting it.
     */
    public static function setConfig(string $key, string $value, bool $encrypt = false): self
    {
        $finalValue = $encrypt ? Crypt::encryptString($value) : $value;
        
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $finalValue]
        );
    }

    /**
     * Get a configuration value, optionally decrypting it.
     */
    public static function getConfig(string $key, $default = null, bool $decrypt = false)
    {
        $setting = self::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        try {
            return $decrypt ? Crypt::decryptString($setting->value) : $setting->value;
        } catch (\Exception $e) {
            return $default;
        }
    }
}
