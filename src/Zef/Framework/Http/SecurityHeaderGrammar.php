<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The security-header audit's value grammar: whether one answered
 * field value satisfies its check's declared kind -- the judgement the
 * {@see SecurityHeaderAudit} walk delegates to, split out of the walk
 * the way the lane's other families split their own folds (the
 * difficulty ceiling's own doctrine: the walk and the grammar it walks
 * with are two units, not one).
 *
 * The four grammars the ruleset spells -- the exact one-value match,
 * the closed one-of set, the non-empty requirement, and the HSTS
 * `max-age` directive -- plus the closed default a hand-constructed
 * check with an unknown kind answers.
 *
 * @internal
 */
final class SecurityHeaderGrammar
{
    /**
     * Whether one field value satisfies its check's grammar.
     *
     * Public for the ruleset's own unit tests: every grammar arm is a
     * judgement worth pinning on its own, including the closed default
     * a hand-constructed check with an unknown kind answers.
     *
     * @param string $value the field's joined value, empty when the field
     *                      carries an empty value
     */
    public static function accepts(SecurityHeaderCheck $securityHeaderCheck, string $value): bool
    {
        return match ($securityHeaderCheck->kind) {
            SecurityHeaderCheck::KIND_EXACT => $value === $securityHeaderCheck->allowedValues[0],
            SecurityHeaderCheck::KIND_ONE_OF => \in_array($value, $securityHeaderCheck->allowedValues, true),
            SecurityHeaderCheck::KIND_NON_EMPTY => $value !== '',
            SecurityHeaderCheck::KIND_MAX_AGE => preg_match('/^max-age=\d+(?:;.++)?$/D', $value) === 1,
            default => false,
        };
    }
}
