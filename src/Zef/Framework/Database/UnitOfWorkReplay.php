<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Closure;
use Throwable;

/**
 * The unit's retrying replay: the flush snapshot re-run per attempt
 * until either it succeeds or the policy is exhausted, with the backoff
 * wait between attempts read through the sleeper seam.
 *
 * @internal
 */
final class UnitOfWorkReplay
{
    /**
     * Replays the snapshot under the policy. Non-retryable throwables
     * and an exhausted policy propagate the last throwable; the waits
     * between attempts are the policy's delays, observed through
     * $sleeper.
     *
     * @param list<callable(ConnectionInterface): void> $operations
     * @param Closure(int): void                        $sleeper
     *
     * @return int the successful attempt's executed count
     */
    public static function run(
        ConnectionInterface $connection,
        array $operations,
        UnitOfWorkRetryPolicy $unitOfWorkRetryPolicy,
        Closure $sleeper,
    ): int {
        $attempt = 0;
        while (true) {
            ++$attempt;

            try {
                return UnitOfWorkFlush::run($connection, $operations);
            } catch (Throwable $throwable) {
                if (
                    !$unitOfWorkRetryPolicy->isRetryable($throwable)
                    || !$unitOfWorkRetryPolicy->shouldRetry($attempt)
                ) {
                    throw $throwable;
                }

                $sleeper($unitOfWorkRetryPolicy->delayMs($attempt));
            }
        }
    }
}
