<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The security-header audit's own unit of declaration: one check the
 * ruleset carries -- the header field it examines, the value grammar
 * that field must satisfy ({@see self::KIND_*}), the values the exact
 * and one-of grammars accept, the tier the check audits at (required
 * findings fail the audit; advisory findings are reported and never
 * fail), whether the check only applies to an https exchange, and the
 * one-line note the renderers spell when the check does not pass.
 *
 * @internal
 */
final readonly class SecurityHeaderCheck
{
    /**
     * The value must equal the one allowed spelling exactly.
     */
    public const string KIND_EXACT = 'exact';

    /**
     * The value must be one of the allowed spellings.
     */
    public const string KIND_ONE_OF = 'one-of';

    /**
     * The value must be non-empty.
     */
    public const string KIND_NON_EMPTY = 'non-empty';

    /**
     * The value must open with a `max-age=` directive.
     */
    public const string KIND_MAX_AGE = 'max-age';

    /**
     * @param non-empty-string       $name          the header field name, in its
     *                                              conventional spelling
     * @param non-empty-string       $kind          one of the {@see self::KIND_*} grammars
     * @param list<non-empty-string> $allowedValues the exact grammar's one value or the
     *                                              one-of grammar's accepted set; empty for
     *                                              the non-empty and max-age grammars
     * @param bool                   $required      whether a finding at this check fails
     *                                              the audit
     * @param bool                   $httpsOnly     whether the check applies only to an
     *                                              https exchange
     * @param non-empty-string       $note          the one-line guidance a finding renders
     */
    public function __construct(
        public string $name,
        public string $kind,
        public array $allowedValues,
        public bool $required,
        public bool $httpsOnly,
        public string $note,
    ) {
    }
}
