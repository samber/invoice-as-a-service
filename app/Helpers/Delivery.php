<?php

namespace App\Helpers;

/**
 * Resolves delivery settings from the instance configuration (config/delivery.php, DELIVERY_* env vars)
 * and the request body.
 */
class Delivery
{
    const CHANNELS = ['s3', 'ftp', 'webhook', 'zapier', 'email'];

    // Casts applied to env strings so the request validator sees the same types as a JSON body.
    const INTEGER_KEYS = ['ftp' => ['port']];
    const BOOLEAN_KEYS = ['ftp' => ['ssl', 'passive']];
    const LIST_KEYS = ['email' => ['cc', 'bcc']];

    public static function perRequestEnabled(): bool
    {
        return (bool) config('delivery.per_request_enabled');
    }

    /**
     * Names of the delivery channels the request body carries a value for.
     * A channel holding only null/empty values (`{"s3": {"presigned_url": null}}`) is not carried.
     */
    public static function channelsInBody(array $data): array
    {
        return array_values(array_filter(self::CHANNELS, function ($channel) use ($data) {
            if (!isset($data[$channel])) {
                return false;
            }
            $value = $data[$channel];

            return is_array($value) ? array_filter($value, fn ($v) => $v !== null && $v !== '' && $v !== []) !== [] : (bool) $value;
        }));
    }

    /**
     * Returns $data with, for each channel, the env defaults completed by the body fields.
     * A body field wins as a whole: lists and header maps are replaced, never appended.
     * Null body values are absent fields; false and 0 are real values.
     */
    public static function withDefaults(array $data): array
    {
        foreach (self::CHANNELS as $channel) {
            $body = $data[$channel] ?? [];
            if (!is_array($body)) {
                continue;
            }

            $merged = array_filter($body, fn ($v) => $v !== null) + self::envDefaults($channel);
            if ($merged !== []) {
                $data[$channel] = $merged;
            }
        }

        return $data;
    }

    private static function envDefaults(string $channel): array
    {
        $defaults = [];

        foreach ((array) config("delivery.{$channel}", []) as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }

            $defaults[$key] = self::cast($channel, $key, $value);
        }

        return array_filter($defaults, fn ($v) => $v !== []);
    }

    private static function cast(string $channel, string $key, mixed $value): mixed
    {
        if (in_array($key, self::INTEGER_KEYS[$channel] ?? [], true)) {
            // A non-numeric value stays a string so the validator reports it.
            return is_numeric($value) ? (int) $value : $value;
        }

        if (in_array($key, self::BOOLEAN_KEYS[$channel] ?? [], true)) {
            return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (in_array($key, self::LIST_KEYS[$channel] ?? [], true)) {
            return array_values(array_filter(array_map('trim', explode(',', (string) $value)), fn ($v) => $v !== ''));
        }

        if ($channel === 'webhook' && $key === 'headers') {
            return self::decodeHeaders($value);
        }

        return $value;
    }

    private static function decodeHeaders(mixed $value): array
    {
        $headers = is_array($value) ? $value : json_decode((string) $value, true);

        // An operator typo must fail every request loudly, not silently drop the auth header.
        if (!is_array($headers) || ($headers !== [] && array_is_list($headers))) {
            throw new \UnexpectedValueException('DELIVERY_WEBHOOK_HEADERS must be a JSON object, e.g. {"x-token":"secret"}');
        }

        return $headers;
    }
}
