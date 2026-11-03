<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Throwable;

/**
 * The transient-failure taxonomy's judgement: the verdict over a
 * thrown failure — the class-name gate first, then (over a non-empty
 * $retryableSqlStates) the code lane's membership
 * ({@see RetryCandidates::carries()}) OR the message lane's containment
 * ({@see RetryMessages::matches()}: the complement that answers the
 * driver failures no informative code accompanies).
 *
 * @internal
 */
final class RetryTaxonomy
{
    /**
     * True when the throwable matches the taxonomy: a class-name match,
     * and -- over a non-empty $retryableSqlStates -- an SQLSTATE match
     * or a message-pattern match.
     *
     * @param list<string> $retryableClassNames
     * @param list<string> $retryableSqlStates
     * @param list<string> $retryableMessagePatterns
     */
    public static function matches(
        Throwable $throwable,
        array $retryableClassNames,
        array $retryableSqlStates,
        array $retryableMessagePatterns,
    ): bool {
        if (!self::matchesClassName($throwable, $retryableClassNames)) {
            return false;
        }

        if ($retryableSqlStates === []) {
            return true;
        }

        return RetryCandidates::carries($throwable, $retryableSqlStates)
            || RetryMessages::matches($throwable->getMessage(), $retryableMessagePatterns);
    }

    /** @param list<string> $retryableClassNames */
    private static function matchesClassName(Throwable $throwable, array $retryableClassNames): bool
    {
        return array_any($retryableClassNames, static fn (string $fqcn): bool => $throwable instanceof $fqcn);
    }
}
