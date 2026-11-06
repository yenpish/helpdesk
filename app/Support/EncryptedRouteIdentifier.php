<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Encodes record route keys without exposing their database identifiers.
 */
final class EncryptedRouteIdentifier
{
    /** @var array<string, string> */
    private static array $encodedIdentifiers = [];

    public static function encode(string $modelClass, mixed $value, ?string $field = null): string
    {
        /** @var Model $model */
        $model = new $modelClass;
        $field ??= $model->getRouteKeyName();

        $cacheKey = $modelClass.'|'.$field.'|'.(string) $value;

        return self::$encodedIdentifiers[$cacheKey] ??= self::encrypt($modelClass, $field, (string) $value);
    }

    private static function encrypt(string $modelClass, string $field, string $value): string
    {
        $ciphertext = Crypt::encryptString(json_encode([
            'version' => 1,
            'model' => $modelClass,
            'field' => $field,
            'value' => $value,
        ], JSON_THROW_ON_ERROR));

        return rtrim(strtr(base64_encode($ciphertext), '+/', '-_'), '=');
    }

    public static function decode(string $token, string $expectedModel, ?string $field = null): ?string
    {
        if ($token === '' || ! preg_match('/\A[A-Za-z0-9_-]+\z/', $token) || strlen($token) % 4 === 1) {
            return null;
        }

        $ciphertext = base64_decode(strtr($token.str_repeat('=', (4 - strlen($token) % 4) % 4), '-_', '+/'), true);
        if ($ciphertext === false) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($ciphertext), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        /** @var Model $model */
        $model = new $expectedModel;
        $fieldParts = $field === null ? [] : explode('.', $field);
        $expectedField = $field === null ? $model->getRouteKeyName() : end($fieldParts);

        if (
            ! is_array($payload)
            || ($payload['version'] ?? null) !== 1
            || ($payload['model'] ?? null) !== $expectedModel
            || ($payload['field'] ?? null) !== $expectedField
            || ! is_string($payload['value'] ?? null)
            || $payload['value'] === ''
        ) {
            return null;
        }

        return $payload['value'];
    }
}
