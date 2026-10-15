<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The auth family's attribute-table census: the entry, key, value and
 * total bounds, and the sensitive-material refusal -- extracted from
 * the request value object as its own unit, the way the reference's
 * own `SecurityRequest` carried it inline.
 *
 * The census is the reference's strictest edge: a key that spells
 * credential material (`authorization`, `token`, `secret`,
 * `password`, ...) is refused outright -- the boundary is exactly the
 * place sensitive bytes must never travel as labelled cargo, and a
 * misspelled probe of that channel is a configuration error, not a
 * value to store.
 */
final class AuthAttributes
{
    /** The attribute table's entry ceiling. */
    public const int MAX_ATTRIBUTES = 16;

    /** One attribute key's ceiling. */
    public const int MAX_ATTRIBUTE_KEY_CHARS = 64;

    /** One attribute value's ceiling. */
    public const int MAX_ATTRIBUTE_VALUE_CHARS = 256;

    /** The attribute table's total budget. */
    public const int MAX_ATTRIBUTE_CHARS = 4096;

    /**
     * The keys no attribute may spell or contain: the reference's own
     * census, as the lowercase comparison reads them.
     */
    private const array FORBIDDEN_KEY_FRAGMENTS = [
        'authorization',
        'proxy-authorization',
        'token',
        'secret',
        'password',
        'private_key',
        'private-key',
    ];

    /**
     * Holds the attribute table's census: the entry, key, value and
     * total bounds, and the sensitive-material refusal.
     *
     * @param array<array-key, mixed> $attributes the table under the census
     *
     * @throws HttpException when a bound is violated or an attribute
     *                       key spells sensitive material
     */
    public static function census(array $attributes): void
    {
        if (\count($attributes) > self::MAX_ATTRIBUTES) {
            throw new HttpException('The security.auth request carries too many attributes.');
        }

        $total = 0;

        foreach ($attributes as $key => $value) {
            if (!\is_string($key) || $key === '' || mb_strlen($key) > self::MAX_ATTRIBUTE_KEY_CHARS) {
                throw new HttpException('The security.auth request carries an invalid attribute key.');
            }

            if (self::spellsCredentialMaterial($key)) {
                throw new HttpException(
                    'The security.auth request attributes cannot carry sensitive credential material.',
                );
            }

            if (!\is_scalar($value) && $value !== null) {
                throw new HttpException('The security.auth request attributes must be scalar or null.');
            }

            $stringValue = $value === null ? '' : (string) $value;

            if (mb_strlen($stringValue) > self::MAX_ATTRIBUTE_VALUE_CHARS) {
                throw new HttpException('The security.auth request attribute value exceeds its bound.');
            }

            $total += mb_strlen($key) + mb_strlen($stringValue);
        }

        if ($total > self::MAX_ATTRIBUTE_CHARS) {
            throw new HttpException('The security.auth request attributes exceed their budget.');
        }
    }

    /**
     * Whether an attribute key spells credential material: the
     * reference's own census, compared against the folded key both
     * whole and as a fragment.
     */
    private static function spellsCredentialMaterial(string $key): bool
    {
        $normalizedKey = mb_strtolower($key);

        return array_any(
            self::FORBIDDEN_KEY_FRAGMENTS,
            static fn (string $forbidden): bool => $normalizedKey === $forbidden
                || str_contains($normalizedKey, $forbidden),
        );
    }
}
