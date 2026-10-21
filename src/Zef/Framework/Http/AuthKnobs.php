<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The auth family's knob census: the nine knobs the family owns, and
 * the rejection an unknown key in the family map answers with --
 * following the CSRF family's own {@see CsrfKnobs} lineage, which
 * followed the rate-limit family's, over the shared {@see
 * StrictKnobs} census the third family made shared property.
 *
 * The known list is spelled out in the rejection: a `principle` typo
 * must never fall through to the default posture unnoticed -- the
 * operator believes every token resolves to their principal while it
 * resolves to the default's.
 */
final class AuthKnobs
{
    /**
     * The knobs the family owns, in declaration order.
     */
    private const array KNOWN = [
        'enabled',
        'tokens',
        'principal',
        'scope',
        'required_scope',
        'allow_anonymous',
        'replay_capacity',
        'replay_window',
        'replay_store',
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
        StrictKnobs::rejectUnknown($map, 'security.auth', self::KNOWN);
    }
}
