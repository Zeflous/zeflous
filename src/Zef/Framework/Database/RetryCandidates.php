<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use PDOException;
use Throwable;

/**
 * The transient-failure taxonomy's candidate fold: the SQLSTATE codes a
 * thrown failure answers, normalised to strings — getCode()'s spelling
 * plus the errorInfo slots a PDOException carries — and the membership
 * judgement over those candidates.
 *
 * @internal
 */
final class RetryCandidates
{
    /**
     * Answers the SQLSTATE candidates the throwable spelled, every one
     * normalised to its string form.
     *
     * @return list<string>
     */
    public static function sqlStates(Throwable $throwable): array
    {
        // Candidates are normalised to string before any strict comparison:
        // PDOException::getCode() returns the SQLSTATE string on Postgres,
        // but the MySQL driver error number as an int (e.g. 1213) — without
        // normalisation, a strict in_array() can never match the int form
        // and MySQL transient errors would silently not retry.
        $candidates = [(string) $throwable->getCode()];
        if ($throwable instanceof PDOException && \is_array($throwable->errorInfo)) {
            foreach ([0, 1] as $offset) {
                /** @var mixed $candidate the slot's raw answer, judged by the guards below */
                $candidate = $throwable->errorInfo[$offset] ?? null;
                if (!\is_string($candidate) && !\is_int($candidate)) {
                    continue;
                }

                $candidates[] = (string) $candidate;
            }
        }

        return $candidates;
    }

    /**
     * True when the throwable's candidate set carries one of
     * $retryableSqlStates — the code lane's membership judgement, the
     * fold's own list walked against the fold's own candidates.
     *
     * @param list<string> $retryableSqlStates
     */
    public static function carries(Throwable $throwable, array $retryableSqlStates): bool
    {
        $candidates = self::sqlStates($throwable);

        return array_any(
            $retryableSqlStates,
            static fn (string $state): bool => \in_array($state, $candidates, true),
        );
    }
}
