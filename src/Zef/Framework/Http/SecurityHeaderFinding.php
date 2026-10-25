<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The security-header audit's answered record: one check's verdict
 * over one response -- the four honest states a check can answer
 * (pass, invalid, missing, not-applicable), the tier the check
 * audited at, and the expected-versus-actual pair the renderers
 * spell.
 *
 * Construction is through the four named constructors only, so a
 * finding can never exist in a state its check did not judge.
 *
 * @internal
 */
final readonly class SecurityHeaderFinding
{
    /**
     * The field is present and its value satisfies the check's grammar.
     */
    public const string VERDICT_PASS = 'pass';

    /**
     * The field is present and its value fails the check's grammar.
     */
    public const string VERDICT_INVALID = 'invalid';

    /**
     * The field is absent from the response.
     */
    public const string VERDICT_MISSING = 'missing';

    /**
     * The check does not apply to this exchange (an https-only check
     * over a cleartext one).
     */
    public const string VERDICT_NOT_APPLICABLE = 'not-applicable';

    /**
     * @param non-empty-string $header   the check's header field name
     * @param non-empty-string $verdict  one of the {@see self::VERDICT_*} states
     * @param bool             $required the check's tier
     * @param string           $expected the grammar's human spelling
     * @param string           $actual   the response's actual value, empty when the
     *                                   field is absent, empty-valued or the check
     *                                   does not apply
     * @param non-empty-string $note     the check's one-line guidance
     */
    private function __construct(
        public string $header,
        public string $verdict,
        public bool $required,
        public string $expected,
        public string $actual,
        public string $note,
    ) {
    }

    /**
     * A passing finding: the field is present and valid.
     *
     * @param string $actual the value that passed
     */
    public static function pass(SecurityHeaderCheck $securityHeaderCheck, string $actual): self
    {
        return new self(
            $securityHeaderCheck->name,
            self::VERDICT_PASS,
            $securityHeaderCheck->required,
            self::expected($securityHeaderCheck),
            $actual,
            $securityHeaderCheck->note,
        );
    }

    /**
     * An invalid finding: the field is present and its value fails the
     * grammar.
     *
     * @param string $actual the value that failed, empty when the field
     *                       carries an empty value
     */
    public static function invalid(SecurityHeaderCheck $securityHeaderCheck, string $actual): self
    {
        return new self(
            $securityHeaderCheck->name,
            self::VERDICT_INVALID,
            $securityHeaderCheck->required,
            self::expected($securityHeaderCheck),
            $actual,
            $securityHeaderCheck->note,
        );
    }

    /**
     * A missing finding: the field is absent from the response.
     */
    public static function missing(SecurityHeaderCheck $securityHeaderCheck): self
    {
        return new self(
            $securityHeaderCheck->name,
            self::VERDICT_MISSING,
            $securityHeaderCheck->required,
            self::expected($securityHeaderCheck),
            '',
            $securityHeaderCheck->note,
        );
    }

    /**
     * A not-applicable finding: the check does not apply to this
     * exchange.
     */
    public static function notApplicable(SecurityHeaderCheck $securityHeaderCheck): self
    {
        return new self(
            $securityHeaderCheck->name,
            self::VERDICT_NOT_APPLICABLE,
            $securityHeaderCheck->required,
            self::expected($securityHeaderCheck),
            '',
            $securityHeaderCheck->note,
        );
    }

    /**
     * Whether this finding is an actionable one (invalid or missing) --
     * the states an operator must answer, whatever the tier.
     */
    public function isFinding(): bool
    {
        return $this->verdict === self::VERDICT_INVALID || $this->verdict === self::VERDICT_MISSING;
    }

    /**
     * The grammar's human spelling, for the renderers.
     */
    private static function expected(SecurityHeaderCheck $securityHeaderCheck): string
    {
        return match ($securityHeaderCheck->kind) {
            SecurityHeaderCheck::KIND_EXACT => $securityHeaderCheck->allowedValues[0],
            SecurityHeaderCheck::KIND_ONE_OF => implode(' | ', $securityHeaderCheck->allowedValues),
            SecurityHeaderCheck::KIND_NON_EMPTY => 'a non-empty value',
            SecurityHeaderCheck::KIND_MAX_AGE => 'max-age=...',
            default => 'a valid value',
        };
    }
}
