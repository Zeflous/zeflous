<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The registration grammar's fold: the version stamp, the name corridor
 * and the duplicate guard a declared migration must clear before the
 * runner books it — pure validation over the registered map, the
 * refusals typed onto the migration phase's own family member.
 *
 * @internal
 */
final class MigrationRegistration
{
    private const string VERSION_RE = '/^\d{14}$/';

    private const int MAX_NAME_BYTES = 128;

    /**
     * Accepts a migration into the registered map, or refuses loudly.
     *
     * @param array<string, MigrationInterface> $registered the versions
     *                                                      already registered on this runner
     *
     * @return array<string, MigrationInterface> the map with the
     *                                           migration booked
     */
    public static function accept(MigrationInterface $migration, array $registered): array
    {
        $version = $migration->version();
        if (preg_match(self::VERSION_RE, $version) !== 1) {
            throw new MigrationException(
                "Migration version '{$version}' must be a 14-digit timestamp (YYYYmmddHHMMSS).",
            );
        }

        $name = $migration->name();
        // The '8bit' encoding IS byte semantics (the BodyBudget
        // doctrine): the corridor counts BYTES over names that may
        // carry multibyte characters, in the spelling the automated
        // mb_str migration cannot rewrite again.
        if ($name === '' || mb_strlen($name, '8bit') > self::MAX_NAME_BYTES) {
            throw new MigrationException(
                'Migration name must be a string of 1..' . self::MAX_NAME_BYTES . ' bytes.',
            );
        }

        if (isset($registered[$version])) {
            throw new MigrationException("Migration version '{$version}' is already registered.");
        }

        $registered[$version] = $migration;

        return $registered;
    }
}
