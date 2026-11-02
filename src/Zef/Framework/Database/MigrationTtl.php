<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The lock TTL corridor: the runner's default TTL validation, and the
 * effective TTL judgement — the migration's explicit override when it
 * provided one (validated positive), the runner's default otherwise.
 *
 * @internal
 */
final class MigrationTtl
{
    /**
     * Refuses a non-positive runner default.
     *
     * @throws MigrationException when the default TTL is not positive
     */
    public static function assertDefault(float $lockTtlSeconds): void
    {
        if ($lockTtlSeconds <= 0.0) {
            throw new MigrationException('Migration lock TTL must be greater than zero.');
        }
    }

    /**
     * Answers the TTL the heartbeat renews with for this step.
     *
     * @throws MigrationException when the override is not positive
     */
    public static function effective(MigrationInterface $migration, float $default): float
    {
        $override = $migration->getLockTtl();
        if ($override === null) {
            return $default;
        }

        if ($override <= 0.0) {
            throw new MigrationException(
                "Migration '{$migration->version()}' lock TTL override must be greater than zero (got {$override}).",
            );
        }

        return $override;
    }
}
