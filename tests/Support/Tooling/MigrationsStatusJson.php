<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The migrations status JSON document: the tool-integration view
 * `bin/zef migrations:status --json` answers -- one record per
 * version in the runner's own order (the version stamp and the
 * declaration's name, null for an applied version whose declaration
 * has since left the directory), the applied and pending sections
 * and the counts closing the document, over the route inspection
 * family's one encoding spelling ({@see RouteInspectionJson::encode})
 * -- the stable shape a deployment pipeline reads the same status
 * through.
 */
final readonly class MigrationsStatusJson
{
    /**
     * The whole status as a JSON document.
     *
     * @param array<string, string> $names   the declared migrations' names keyed
     *                                       by version stamp
     * @param list<string>          $applied versions recorded as applied, ascending
     * @param list<string>          $pending versions migrate() would apply right now
     */
    public static function rendered(array $names, array $applied, array $pending): string
    {
        return RouteInspectionJson::encode([
            'applied' => self::records($names, $applied),
            'pending' => self::records($names, $pending),
            'counts' => [
                'applied' => \count($applied),
                'pending' => \count($pending),
                'declared' => \count($names),
            ],
        ]);
    }

    /**
     * One record per version: the stamp and the declaration's name,
     * null when the declaration has since left the directory.
     *
     * @param array<string, string> $names    the declared migrations' names keyed
     *                                        by version stamp
     * @param list<string>          $versions the section's versions, in order
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
