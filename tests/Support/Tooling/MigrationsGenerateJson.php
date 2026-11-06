<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The migrations generate verb's JSON document: the tool-integration
 * view `bin/zef migrations:generate --json` answers -- the created
 * file's path, its version stamp and its canonical name, over the
 * route inspection family's one encoding spelling ({@see
 * RouteInspectionJson::encode}) -- the stable shape a deployment
 * pipeline scaffolds the same migration through.
 */
final readonly class MigrationsGenerateJson
{
    /**
     * The created migration file's answer as a JSON document.
     *
     * @param non-empty-string $version the created migration's 14-digit version stamp
     * @param non-empty-string $name    the created migration's canonical name
     * @param non-empty-string $file    the created migration file's path
     */
    public static function rendered(string $version, string $name, string $file): string
    {
        return RouteInspectionJson::encode([
            'created' => $file,
            'version' => $version,
            'name' => $name,
        ]);
    }
}
