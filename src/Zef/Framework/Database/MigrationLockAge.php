<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The lock row's age judgement: whether the holder a would-be stealer
 * found is still inside the TTL recorded ON THE ROW — the row's own
 * declaration is the authority, never the recovering runner's default.
 *
 * @internal
 */
final class MigrationLockAge
{
    /**
     * Refuses the steal while the declared holder is still inside its
     * own recorded TTL: the age the injected clock answers is compared
     * against the row's decoded TTL, and a malformed row (missing or
     * non-numeric fields) is a bookkeeping corruption the runner halts
     * on rather than guesses past.
     *
     * @param array<string, mixed> $row the lock row as fetched
     * @param int                  $now wall-clock unix seconds
     *
     * @throws QueryException       when the lock row is malformed
     * @throws TransactionException while the declared holder's TTL holds
     */
    public static function assertStealable(array $row, int $now): void
    {
        $lockedRaw = $row['locked_at'] ?? null;
        $ttlRaw = $row['ttl'] ?? null;
        if (
            (!\is_int($lockedRaw) && !\is_float($lockedRaw) && !\is_string($lockedRaw))
            || (!\is_int($ttlRaw) && !\is_float($ttlRaw) && !\is_string($ttlRaw))
        ) {
            throw new QueryException('Migration lock row is malformed.');
        }

        $lockedAt = (int) $lockedRaw;
        $rowTtl = self::decodedTtl($ttlRaw);
        $age = $now - $lockedAt;
        if ((float) $age < $rowTtl) {
            // The cast on its own assignment is the one spelling both
            // psalm and rector leave alone (psalm refuses the implicit
            // float concat; rector strips the inline concat-operand cast).
            $ttlSpelling = (string) $rowTtl;

            throw new TransactionException(
                'Migration lock is already held (age ' . $age . 's, ttl ' . $ttlSpelling . 's).',
            );
        }
    }

    /**
     * The row's TTL in its float form: a non-numeric string folds to
     * zero (the engine's own coercion), so a holder that declared
     * garbage holds nothing.
     */
    private static function decodedTtl(float|int|string $ttlRaw): float
    {
        return (float) $ttlRaw;
    }
}
