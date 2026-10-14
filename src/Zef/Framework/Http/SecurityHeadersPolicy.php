<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The security-headers family's validated knob set: the `security.headers`
 * configuration subtree as one immutable value object.
 *
 * Construction through {@see self::fromMap()} is the config-driven path --
 * the map the layered builder hands over after Default < App < Env merging,
 * where a knob's value can be the native bool a file declared or the raw
 * string an environment overlay wrote over it. The reading is strict on
 * both spellings: a native non-bool is rejected (a file that writes `1`
 * meant `true` and mistyped it), and a string is accepted only in the
 * closed boolean vocabulary (`1`/`true`/`on`/`yes` and their negations,
 * case-insensitive) -- anything else (`maybe`, a mistyped `ture`) halts the
 * boot with the knob named, because a silently-mistyped security knob is
 * a protection the operator believes is on while it is not. This is the
 * reference's `readBoolStrict` lineage (legacy `zef-framework`
 * `app/Bootstrap.php`), kept fail-closed. Unknown keys are rejected with
 * the known list spelled out (the restored reference's strict-mode
 * lineage, `ConfigSchemaValidator` with `allowUnknownKeys=false`): a
 * `htss` typo must never fall through to the defaults unnoticed.
 *
 * A `null` map is the absent family -- the empty map -- and reads as the
 * constructor defaults, which mirror the framework profile's own
 * declaration ({@see \Zef\Framework\FrameworkDefaults}); a dedicated boot
 * test pins the two declarations together so they cannot drift apart.
 *
 * The knobs and their defaults follow the reference implementation's
 * posture (legacy `zef-main` `Middleware/SecurityHeadersMiddleware.php`,
 * milestone E2): the six baseline hardening headers are always on and are
 * not knobs at all ({@see BaselineSecurityHeaders}); `permissions_policy`
 * defaults to on; `hsts` and `csp` default to off -- opt-in toggles over a
 * hardened baseline, never the other way around.
 */
final readonly class SecurityHeadersPolicy
{
    private const string DEFAULT_CONTENT_SECURITY_POLICY
        = "default-src 'self'; frame-ancestors 'none'; base-uri 'self'";

    /**
     * The strict boolean vocabulary's truth spellings, comparison form.
     */
    private const array TRUE_WORDS = ['1', 'true', 'on', 'yes'];

    /**
     * The strict boolean vocabulary's falsehood spellings, comparison form.
     */
    private const array FALSE_WORDS = ['0', 'false', 'off', 'no', ''];

    /**
     * @throws HttpException when the map is not a map, carries an unknown
     *                       knob, or a knob's value is neither a boolean nor
     *                       one of the strict boolean string spellings
     */
    public function __construct(
        public bool $hsts = false,
        public bool $contentSecurityPolicyEnabled = false,
        public string $contentSecurityPolicy = self::DEFAULT_CONTENT_SECURITY_POLICY,
        public bool $permissionsPolicy = true,
    ) {
        if ($this->contentSecurityPolicy === '') {
            throw new HttpException(
                'The security.headers content_security_policy knob must be a non-empty string.',
            );
        }
    }

    /**
     * Reads the merged `security.headers` map into the policy.
     *
     * @param mixed $map the subtree as the frozen repository carries it: a
     *                   knob map, or null when the family is absent
     *
     * @throws HttpException when the value is neither a map nor null
     */
    public static function fromMap(mixed $map): self
    {
        if ($map === null) {
            return new self();
        }

        if (!\is_array($map)) {
            throw new HttpException(
                'The security.headers configuration must be a map, ' . self::received($map),
            );
        }

        self::rejectUnknownKnobs($map);

        return new self(
            hsts: self::toggle($map['hsts'] ?? false, 'hsts'),
            contentSecurityPolicyEnabled: self::toggle($map['csp'] ?? false, 'csp'),
            contentSecurityPolicy: self::policyString(
                $map['content_security_policy'] ?? self::DEFAULT_CONTENT_SECURITY_POLICY,
            ),
            permissionsPolicy: self::toggle($map['permissions_policy'] ?? true, 'permissions_policy'),
        );
    }

    /**
     * The map's own key census: every knob it carries must be one of the
     * four the family owns, named in the rejection with the known list
     * spelled out -- a `htss` typo must never fall through to the default
     * posture unnoticed.
     *
     * @param array<array-key, mixed> $map
     */
    private static function rejectUnknownKnobs(array $map): void
    {
        $known = ['hsts', 'csp', 'content_security_policy', 'permissions_policy'];

        foreach (array_keys($map) as $knob) {
            if (!\is_string($knob) || !\in_array($knob, $known, true)) {
                throw new HttpException(
                    "The security.headers configuration carries the unknown knob '"
                    . (\is_string($knob) ? $knob : get_debug_type($knob))
                    . "' (known knobs: hsts, csp, content_security_policy, permissions_policy).",
                );
            }
        }
    }

    /**
     * The strict boolean reading of one toggle knob's value: a native
     * bool passes through, a string folds through the Unicode case map
     * into the closed vocabulary, and anything else is rejected with the
     * knob named. The call site owns the knob's default -- an absent (or
     * explicitly null) knob never reaches this reader, because `??`
     * already substituted the family's own default posture.
     *
     * The fold is value-equivalent under the MBString mutator on this
     * method by construction: the only single-character lower-case landing
     * into ASCII the Unicode case map carries is U+212A to `k`, no
     * vocabulary word contains a `k`, so every fold-divergent value is
     * rejected under both spellings -- the zone is answered with a
     * method-scoped ignore entry argued in `infection.json5` (the
     * ETagMiddleware method-token fold precedent).
     */
    private static function toggle(mixed $value, string $knob): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            $folded = mb_strtolower($value);

            if (\in_array($folded, self::TRUE_WORDS, true)) {
                return true;
            }

            if (\in_array($folded, self::FALSE_WORDS, true)) {
                return false;
            }
        }

        throw new HttpException(
            "The security.headers {$knob} knob must be a boolean or one of the boolean string "
            . 'spellings (true/false, on/off, yes/no, 1/0), ' . self::received($value),
        );
    }

    /**
     * The content-security-policy string knob's value: the value the call
     * site read (the map's own string, or the default policy the `??`
     * substitution answered for an absent or explicitly null knob). A
     * non-string value is a configuration error; an explicitly empty
     * string is rejected by the constructor's own invariant, not a header
     * without a value.
     */
    private static function policyString(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        throw new HttpException(
            'The security.headers content_security_policy knob must be a string, '
            . self::received($value),
        );
    }

    /**
     * The "as the configuration carried it" suffix every knob failure
     * closes with: the value's runtime type, in the engine's own spelling.
     */
    private static function received(mixed $value): string
    {
        return get_debug_type($value) . ' given.';
    }
}
