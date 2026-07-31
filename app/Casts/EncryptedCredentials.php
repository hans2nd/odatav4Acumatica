<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class EncryptedCredentials implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return array|null
     */
    public function get($model, $key, $value, $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        try {
            // Attempt to decrypt
            $decrypted = Crypt::decryptString($value);
            return json_decode($decrypted, true);
        } catch (DecryptException $e) {
            // Backward compatibility for existing plaintext JSON records
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            return []; // Fallback to empty array if decoding fails
        }
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  array  $value
     * @param  array  $attributes
     * @return string|null
     */
    public function set($model, $key, $value, $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        // Encrypt the JSON encoded array
        return Crypt::encryptString(json_encode($value));
    }
}
