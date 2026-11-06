<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The migrations rollback verb's JSON document: the machine-readable
 * answer `bin/zef migrations:rollback --json` spells -- the versions
 * the rollback reverted, newest-first the runner reverts them, each
 * carrying its declared name (null for a version whose declaration
 * has since left the directory, the status document's own honest
 * spelling), over the route inspection family's one encoding spelling
 * ({@see RouteInspectionJson::encode}) -- the same facts the text
 * renderer carries, so a pipeline and an operator read one truth.
 */
final readonly class MigrationsRollbackJson
{
    /**
     * The rollback's answer as a JSON document.
     *
     * @param array<string, string> $names    the declared migrations' names keyed
     *                                        by version stamp
     * @param list<string>          $versions the versions the rollback reverted, in order
     */
    public static function rolledBack(array $names, array $versions): string
    {
        return RouteInspectionJson::encode([
            'rolledBack' => self::records($names, $versions),
        ]);
    }

    /**
     * One record per version: the stamp and the declaration's name,
     * null when the declaration has since left the directory.
     *
     * @param array<string, string> $names    the declared migrations' names keyed
     *                                        by version stamp
     * @param list<string>          $versions the versions the rollback reverted, in order
     *
     * @return list<array{version: string, name: null|string}>
     */
    private static function records(array $names, array $versions): array
    {
        $records = [];

        foreach ($versions as $version) {
            $records[] = [
                'version' => $version,
                'name' => $names[$version] ?? null,
            ];
        }

        return $records;
    }
}
