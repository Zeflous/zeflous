<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The runner's pending walk: the registered map minus the applied
 * versions, ascending — the plan migrate() spells and plan() names.
 *
 * @internal
 */
final class MigrationPending
{
    /**
     * Answers the registered-but-not-applied migrations, ascending.
     *
     * @param array<string, MigrationInterface> $registered the runner's registrations
     * @param list<string>                      $applied    the versions already recorded
     *
     * @return list<MigrationInterface>
     */
    public static function migrations(array $registered, array $applied): array
    {
        $done = array_fill_keys($applied, true);
        $versions = array_keys($registered);
        sort($versions);
        $pending = [];
        foreach ($versions as $version) {
            if (isset($done[$version])) {
                continue;
            }

            $pending[] = $registered[$version];
        }

        return $pending;
    }

    /**
     * Answers the versions the pending migrations spell, in order —
     * the plan itself.
     *
     * @param list<MigrationInterface> $migrations
     *
     * @return list<string>
     */
    public static function versions(array $migrations): array
    {
        return array_map(
            static fn (MigrationInterface $migration): string => $migration->version(),
            $migrations,
        );
    }

    /**
     * Answers the versions migrate() would apply right now — the plan
     * over the registered map and the applied versions.
     *
     * @param array<string, MigrationInterface> $registered
     * @param list<string>                      $applied
     *
     * @return list<string>
     */
    public static function plan(array $registered, array $applied): array
    {
        return self::versions(self::migrations($registered, $applied));
    }
}
