<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The session family's knob census: the nine knobs the family owns, and
 * the rejection an unknown key in the family map answers with -- following
 * the body-parser family's own {@see BodyParserKnobs} lineage over the
 * shared {@see StrictKnobs} census the whole config-driven lineage reads.
 *
 * The known list is spelled out in the rejection: a `same_sitee` typo
 * must never fall through to the default posture unnoticed -- the
 * operator believes their session cookie carries `SameSite=Strict`
 * while the default's `Lax` is what the browser actually received,
 * and a deployment that thinks its session is pinned to first-party
 * context is silently riding the looser cross-site posture.
 */
final class SessionKnobs
{
    /**
     * The knobs the family owns, in declaration order.
     */
    private const array KNOWN = [
        'enabled',
        'cookie_name',
        'idle_timeout',
        'id_bytes',
        'cookie_secure',
        'cookie_http_only',
        'same_site',
        'store',
        'path',
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
        StrictKnobs::rejectUnknown($map, 'http.session', self::KNOWN);
    }
}
