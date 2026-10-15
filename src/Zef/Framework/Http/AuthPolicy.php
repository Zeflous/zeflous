<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The auth family's validated knob set: the `security.auth`
 * configuration subtree as one immutable value object -- following the
 * CSRF family's own {@see CsrfPolicy} lineage, which followed the
 * rate-limit family's.
 *
 * Construction through {@see self::fromMap()} is the config-driven
 * path -- the merged map the layered builder hands over, where a
 * knob's value can be the native type a file declared or the raw
 * string an environment overlay wrote over it. Every knob is read
 * strictly through the family's shared readers ({@see StrictToggles}
 * for the toggles, {@see StrictIntegers} for the replay pair, the
 * private readers for the token table and the principal fields), the
 * unknown-key census is {@see AuthKnobs}, and the bounds are {@see
 * AuthBounds}: a wrong type, an out-of-range value, a malformed
 * token or an unknown key halts the boot with the knob named.
 *
 * A `null` map is the absent family and reads as the constructor
 * defaults, which mirror the framework profile's own declaration
 * ({@see \Zef\Framework\FrameworkDefaults}); the boot test pins the
 * two declarations together so they cannot drift.
 *
 * The knobs and their defaults follow the reference implementation's
 * posture (legacy `zef-main` `Framework/Security/Distributed/`): the
 * family is armed by default (the fail-safe default-on the CSRF
 * family already ported -- a deployment that adds the middleware to
 * its stack is protected without flipping anything), the token table
 * ships empty (the empty default is the "not configured" the
 * *resolution* halts on -- {@see AuthBounds::assertUsableTokens()},
 * at the factory the stack membership triggered -- never a token the
 * framework made up; the values are runtime material, and the
 * environment overlay `APP_SECURITY__AUTH__TOKENS` is where they
 * belong, exactly as the CSRF family's secret does), the static
 * table's principal and scope are `api`, the required scope matches,
 * anonymous is refused, and the replay table holds 1024 ids for 300
 * seconds. One deliberate deviation: the reference's static provider
 * accepted any non-empty token, and this port holds the 32-character
 * entropy floor ({@see AuthBounds}) -- a brute-forceable credential
 * is a configuration error, not a posture.
 */
final readonly class AuthPolicy
{
    private const string DEFAULT_PRINCIPAL = 'api';

    private const string DEFAULT_SCOPE = 'api';

    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /**
     * @param list<string> $tokens         the static bearer token table,
     *                                     each entry a grammar-valid
     *                                     32-to-512-character token
     * @param int          $replayCapacity the replay table's entry
     *                                     ceiling (>= 1)
     * @param int          $replayWindow   the replay duplicate window,
     *                                     in seconds (>= 1)
     *
     * @throws HttpException when a token violates its shape, a field
     *                       violates its bound, or a replay knob is
     *                       out of range
     */
    public function __construct(
        public bool $enabled = true,
        public array $tokens = [],
        public string $principal = self::DEFAULT_PRINCIPAL,
        public string $scope = self::DEFAULT_SCOPE,
        public string $requiredScope = self::DEFAULT_SCOPE,
        public bool $allowAnonymous = false,
        public int $replayCapacity = 1024,
        public int $replayWindow = 300,
    ) {
        foreach ($this->tokens as $token) {
            AuthBounds::assertTokenShape($token);
        }

        if ($this->replayCapacity < 1) {
            throw new HttpException('The security.auth replay_capacity knob must be 1 or above.');
        }

        if ($this->replayWindow < 1) {
            throw new HttpException('The security.auth replay_window knob must be 1 or above.');
        }
    }

    /**
     * Reads the merged `security.auth` map into the policy.
     *
     * @param mixed $map the subtree as the frozen repository carries it: a
     *                   knob map, or null when the family is absent
     *
     * @throws HttpException when the value is neither a map nor null, or a
     *                       knob fails its strict reading
     */
    public static function fromMap(mixed $map): self
    {
        if ($map === null) {
            return new self();
        }

        if (!\is_array($map)) {
            throw new HttpException(
                'The security.auth configuration must be a map, ' . get_debug_type($map) . self::GIVEN_SUFFIX,
            );
        }

        AuthKnobs::rejectUnknown($map);

        return new self(...self::knobs($map));
    }

    /**
     * The family's knob map as constructor arguments: the arm, the
     * token table, the principal fields, the anonymous switch and the
     * replay pair -- read strictly, every one.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array{enabled: bool, tokens: list<string>, principal: string,
     *               scope: string, requiredScope: string, allowAnonymous: bool,
     *               replayCapacity: int, replayWindow: int}
     */
    private static function knobs(array $map): array
    {
        return [
            'enabled' => StrictToggles::read($map['enabled'] ?? true, 'security.auth enabled'),
            'tokens' => AuthTokenKnobs::read($map['tokens'] ?? []),
            'principal' => AuthFieldKnobs::read($map['principal'] ?? self::DEFAULT_PRINCIPAL, 'principal'),
            'scope' => AuthFieldKnobs::read($map['scope'] ?? self::DEFAULT_SCOPE, 'scope'),
            'requiredScope' => AuthFieldKnobs::read(
                $map['required_scope'] ?? self::DEFAULT_SCOPE,
                'required_scope',
            ),
            'allowAnonymous' => StrictToggles::read(
                $map['allow_anonymous'] ?? false,
                'security.auth allow_anonymous',
            ),
            'replayCapacity' => StrictIntegers::read(
                $map['replay_capacity'] ?? 1024,
                'security.auth replay_capacity',
            ),
            'replayWindow' => StrictIntegers::read($map['replay_window'] ?? 300, 'security.auth replay_window'),
        ];
    }
}
