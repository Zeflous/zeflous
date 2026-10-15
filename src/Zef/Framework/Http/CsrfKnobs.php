<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The CSRF family's knob census: the eight knobs the family owns, and
 * the rejection an unknown key in the family map answers with.
 *
 * The known list is spelled out in the rejection (the restored
 * reference's strict-mode lineage, `ConfigSchemaValidator` with
 * `allowUnknownKeys=false`): a `coookie_name` typo must never fall
 * through to the default posture unnoticed -- the operator believes the
 * token travels under the name they wrote while it travels under the
 * default's.
 */
final class CsrfKnobs
{
    /**
     * The knobs the family owns, in declaration order.
     */
    private const array KNOWN = [
        'enabled',
        'secret',
        'cookie_name',
        'header_name',
        'token_bytes',
        'cookie_secure',
        'cookie_http_only',
        'same_site',
    ];

    /**
     * Rejects the map's unknown keys with the known list spelled out.
     *
     * @param array<array-key, mixed> $map the family's knob map
     *
     * @throws HttpException when the map carries an unknown key
     */
    public static function rejectUnknown(array $map): void
    {
        foreach (array_keys($map) as $knob) {
            if (\is_string($knob) && \in_array($knob, self::KNOWN, true)) {
                continue;
            }

            throw new HttpException(
                "The security.csrf configuration carries the unknown knob '"
                . (\is_string($knob) ? $knob : get_debug_type($knob))
                . "' (known knobs: enabled, secret, cookie_name, header_name, token_bytes,"
                . ' cookie_secure, cookie_http_only, same_site).',
            );
        }
    }
}
