<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The outcome a match result settled on, in the explanation
 * family's own closed spellings: the fold the span's status and the
 * meter's dimensions both read, so the answer, its text and its JSON
 * all spell the outcomes identically.
 *
 * Extracted from {@see RouteMatchExplanation} (#795's gate round)
 * because the method-count ceiling (Sonar S1448, 20 authorized)
 * scores an explanation value object that also owns the foreign-type
 * fold above the limit -- the same doctrine the archeology
 * difficulty ceiling taught the difficulty split: the vocabulary a
 * fold consults belongs to its own unit. The spellings themselves
 * stay owned by the explanation ({@see
 * RouteMatchExplanation::OUTCOME_*}); this fold only translates a
 * {@see MatchResult} into them.
 *
 * @internal
 */
final class RouteMatchOutcome
{
    /**
     * The outcome name a match result answers, in the explanation
     * family's own vocabulary.
     */
    public static function of(MatchResult $matchResult): string
    {
        return match (true) {
            $matchResult->isFound() => RouteMatchExplanation::OUTCOME_FOUND,
            $matchResult->isMethodNotAllowed() => RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED,
            $matchResult->isNotAcceptable() => RouteMatchExplanation::OUTCOME_NOT_ACCEPTABLE,
            default => RouteMatchExplanation::OUTCOME_NOT_FOUND,
        };
    }
}
