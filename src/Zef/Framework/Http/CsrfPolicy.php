<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The CSRF family's validated knob set: the `security.csrf` configuration
 * subtree as one immutable value object.
 *
 * Construction through {@see self::fromMap()} is the config-driven path --
 * the merged map the layered builder hands over, where a knob's value can
 * be the native type a file declared or the raw string an environment
 * overlay wrote over it. Every knob is read strictly through the family's
 * shared readers ({@see StrictToggles} for the toggles, {@see
 * StrictIntegers} for the entropy, {@see SameSiteVocabulary} for the SameSite
 * vocabulary, the private name reader for the two field names), the
 * unknown-key census is {@see CsrfKnobs}, and the bounds are {@see
 * CsrfBounds}: a wrong type, an out-of-range value, an unknown vocabulary
 * word or an unknown key halts the boot with the knob named.
 *
 * A `null` map is the absent family and reads as the constructor
 * defaults, which mirror the framework profile's own declaration ({@see
 * \Zef\Framework\FrameworkDefaults}); the boot test pins the two
 * declarations together so they cannot drift.
 *
 * The knobs and their defaults follow the reference implementation's
 * posture (legacy `zef-main` `Framework/Security/SecurityPolicy.php`):
 * the family is armed by default (the reference's fail-safe default-on --
 * a deployment that adds the middleware to its stack is protected without
 * flipping anything), no secret ships in the profile (the empty string is
 * the "not configured" the *resolution* halts on -- {@see
 * CsrfBounds::assertUsableSecret()}, at the factory the stack membership
 * triggered -- never a default the manager would sign with), the
 * cookie/header pair is `ZEF-XSRF-TOKEN` / `X-CSRF-Token`, 32 bytes of
 * entropy, and the cookie posture is Secure + HttpOnly + SameSite Strict.
 * One deliberate deviation, argued in {@see CsrfBounds}: the reference
 * disabled the family with an `error_log` warning when the default-on
 * posture met an absent secret; this port halts at the resolution,
 * because stack membership is already the explicit opt-in and a
 * logged-and-disabled protection is a protection the operator believes
 * is running.
 */
final readonly class CsrfPolicy
{
    private const string DEFAULT_COOKIE_NAME = 'ZEF-XSRF-TOKEN';

    private const string DEFAULT_HEADER_NAME = 'X-CSRF-Token';

    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /**
     * The RFC 9110 token grammar a cookie or header field name must
     * carry: the reference's own validation regex, move-verbatim.
     */
    private const string TOKEN_NAME_GRAMMAR = "/^[A-Za-z0-9!#$%&'*+.^_`|~-]+$/D";

    /**
     * @throws HttpException when a bound is violated, a field name is not
     *                       a token, or the SameSite vocabulary requires
     *                       a Secure cookie the knobs did not declare
     */
    public function __construct(
        public bool $enabled = true,
        public string $secret = '',
        public string $cookieName = self::DEFAULT_COOKIE_NAME,
        public string $headerName = self::DEFAULT_HEADER_NAME,
        public int $tokenBytes = 32,
        public bool $cookieSecure = true,
        public bool $cookieHttpOnly = true,
        public string $sameSite = SameSiteVocabulary::STRICT,
    ) {
        CsrfBounds::assert($this->tokenBytes);

        $this->assertTokenName($this->cookieName, 'cookie_name');
        $this->assertTokenName($this->headerName, 'header_name');

        if ($this->sameSite === SameSiteVocabulary::NONE && !$this->cookieSecure) {
            throw new HttpException(
                'The security.csrf same_site None knob requires the Secure cookie attribute'
                . ' (cookie_secure).',
            );
        }
    }

    /**
     * Reads the merged `security.csrf` map into the policy.
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
                'The security.csrf configuration must be a map, ' . get_debug_type($map) . self::GIVEN_SUFFIX,
            );
        }

        CsrfKnobs::rejectUnknown($map);

        return new self(...self::knobs($map));
    }

    /**
     * The family's knob map as constructor arguments: the arm, the
     * secret, the field names, the entropy, and the cookie posture --
     * read strictly, every one.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array{enabled: bool, secret: string, cookieName: string,
     *               headerName: string, tokenBytes: int, cookieSecure: bool,
     *               cookieHttpOnly: bool, sameSite: string}
     */
    private static function knobs(array $map): array
    {
        return [
            'enabled' => StrictToggles::read($map['enabled'] ?? true, 'security.csrf enabled'),
            'secret' => self::secretString($map['secret'] ?? ''),
            'cookieName' => self::tokenName($map['cookie_name'] ?? self::DEFAULT_COOKIE_NAME, 'cookie_name'),
            'headerName' => self::tokenName($map['header_name'] ?? self::DEFAULT_HEADER_NAME, 'header_name'),
            'tokenBytes' => StrictIntegers::read($map['token_bytes'] ?? 32, 'security.csrf token_bytes'),
            'cookieSecure' => StrictToggles::read($map['cookie_secure'] ?? true, 'security.csrf cookie_secure'),
            'cookieHttpOnly' => StrictToggles::read($map['cookie_http_only'] ?? true, 'security.csrf cookie_http_only'),
            'sameSite' => SameSiteVocabulary::read($map['same_site'] ?? SameSiteVocabulary::STRICT, 'security.csrf'),
        ];
    }

    /**
     * The signing secret's reading: the value the call site read (the
     * map's own string, or the empty default the `??` substitution
     * answered for an absent knob). A non-string value is a
     * configuration error; the empty string is the "not configured" the
     * bounds read when the family is armed.
     */
    private static function secretString(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        throw new HttpException(
            'The security.csrf secret knob must be a string, ' . get_debug_type($value) . self::GIVEN_SUFFIX,
        );
    }

    /**
     * One field name's reading: the value the call site read, validated
     * against the RFC 9110 token grammar -- a name with a space or a
     * non-token byte is a cookie or header that could never travel.
     *
     * @param string $knob the knob's own name, as the rejection spells it
     */
    private static function tokenName(mixed $value, string $knob): string
    {
        if (\is_string($value) && preg_match(self::TOKEN_NAME_GRAMMAR, $value) === 1) {
            return $value;
        }

        throw new HttpException(
            'The security.csrf ' . $knob . ' knob must be a header-field name (RFC 9110 token), '
            . (\is_string($value) ? "'" . $value . "'" : get_debug_type($value)) . self::GIVEN_SUFFIX,
        );
    }

    /**
     * One field name's constructor invariant: the same grammar the
     * reading enforces, held for hand construction too.
     */
    private function assertTokenName(string $name, string $knob): void
    {
        if (preg_match(self::TOKEN_NAME_GRAMMAR, $name) !== 1) {
            throw new HttpException(
                'The security.csrf ' . $knob . ' knob must be a header-field name (RFC 9110 token).',
            );
        }
    }
}
