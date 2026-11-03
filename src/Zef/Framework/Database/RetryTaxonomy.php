<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Throwable;

/**
 * The transient-failure taxonomy's judgement: whether a thrown failure
 * matches the retryable class names (instanceof), and -- over a
 * non-empty $retryableSqlStates -- whether the SQLSTATE the driver
 * reported is one of the retryable codes OR the message carries one
 * of the retryable lost-connection patterns ({@see RetryMessages}:
 * the complement that answers the driver failures no informative
 * code accompanies).
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

        return self::matchesSqlState($throwable, $retryableSqlStates)
            || RetryMessages::matches($throwable->getMessage(), $retryableMessagePatterns);
    }

    /** @param list<string> $retryableClassNames */
    private static function matchesClassName(Throwable $throwable, array $retryableClassNames): bool
    {
        return array_any($retryableClassNames, static fn (string $fqcn): bool => $throwable instanceof $fqcn);
    }

    /** @param list<string> $retryableSqlStates */
    private static function matchesSqlState(Throwable $throwable, array $retryableSqlStates): bool
    {
        $candidates = RetryCandidates::sqlStates($throwable);

        return array_any(
            $retryableSqlStates,
            static fn (string $state): bool => \in_array($state, $candidates, true),
        );
    }
}
