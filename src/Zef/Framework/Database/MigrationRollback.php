<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The rollback's target selection: the most recent applied versions,
 * descending, at most $steps of them — with the too-few-applied refusal
 * and the unregistered-version lookup named first.
 *
 * @internal
 */
final class MigrationRollback
{
    /**
     * Refuses a non-positive step count before any lock is touched.
     *
     * @throws MigrationException when $steps is not positive
     */
    public static function assertSteps(int $steps): void
    {
        if ($steps < 1) {
            throw new MigrationException('Rollback steps must be >= 1.');
        }
    }

    /**
     * Answers the versions the rollback walks, newest first.
     *
     * @param list<string> $applied the versions recorded as applied, ascending
     *
     * @return list<string>
     *
     * @throws MigrationException when fewer than $steps are applied
     */
    public static function targets(array $applied, int $steps): array
    {
        $targets = \array_slice(array_reverse($applied), 0, $steps);
        if (\count($targets) < $steps) {
            throw new MigrationException(
                "Requested {$steps} rollback step(s) but only " . \count($targets) . ' applied migration(s) exist.',
            );
        }

        return $targets;
    }

    /**
     * Answers the registered migration behind an applied version.
     *
     * @param array<string, MigrationInterface> $registered
     *
     * @throws MigrationException when the version has no registration
     */
    public static function migration(array $registered, string $version): MigrationInterface
    {
        return $registered[$version]
            ?? throw new MigrationException(
                "Applied migration '{$version}' is not registered; cannot roll back.",
            );
    }
}
