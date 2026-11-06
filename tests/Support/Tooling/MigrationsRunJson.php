<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The migrations run verb's JSON document: the machine-readable answer
 * `bin/zef migrations:run --json` spells -- the versions the run
 * applied, in the runner's own order, each carrying its declared name
 * (null for an applied version whose declaration has since left the
 * directory, the status document's own honest spelling), over the
 * route inspection family's one encoding spelling ({@see
 * RouteInspectionJson::encode}) -- the same facts the text renderer
 * carries, so a pipeline and an operator read one truth.
 */
final readonly class MigrationsRunJson
{
    /**
     * The run's answer as a JSON document.
     *
     * @param array<string, string> $names    the declared migrations' names keyed
     *                                        by version stamp
     * @param list<string>          $versions the versions the run applied, in order
     */
    public static function applied(array $names, array $versions): string
    {
        return RouteInspectionJson::encode([
            'applied' => self::records($names, $versions),
        ]);
    }

    /**
     * One record per version: the stamp and the declaration's name,
     * null when the declaration has since left the directory.
     *
     * @param array<string, string> $names    the declared migrations' names keyed
     *                                        by version stamp
     * @param list<string>          $versions the versions the run applied, in order
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
