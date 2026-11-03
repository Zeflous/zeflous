<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The manifest's path-resolution fold: the declaration's three optional
 * extension-point paths (`routes`, `events`, `cli`) resolved against
 * the manifest's own directory -- the one spelling that knows where a
 * relative path is relative to. The `config_schema` path rides the
 * same spelling through {@see resolvedPath()} at the manifest grammar's
 * own seam.
 *
 * The fold is the manifest grammar's own ({@see ModuleManifest::parsed()}
 * delegates here) because the resolution is one rule repeated over the
 * four declarations: an optional path, kept raw at parse time for
 * exactly this fold to resolve, a rooted spelling riding verbatim and
 * the `./` prefix being the same-directory sugar. The class exists as
 * its own fold so the manifest's grammar stays the shape question --
 * the archeology ceiling's own ask, the migrations lane's settle
 * precedent replayed.
 */
final readonly class ModuleManifestPaths
{
    /**
     * Answers the declaration with its four optional paths resolved
     * against the manifest file's own directory.
     */
    /**
     * Answers the declaration with its three extension-point paths
     * resolved against the manifest's own directory -- the routes,
     * events and CLI halves ride the one fold because they ride the one
     * rule: an optional declaration the discovery fold resolves to its
     * absolute spelling.
     */
    public function resolved(
        ModuleDeclaration $moduleDeclaration,
        string $manifest,
    ): ModuleDeclaration {
        if ($moduleDeclaration->routes !== null) {
            $moduleDeclaration = $moduleDeclaration->withRoutes(
                self::resolvedPath($moduleDeclaration->routes, $manifest),
            );
        }

        if ($moduleDeclaration->events !== null) {
            $moduleDeclaration = $moduleDeclaration->withEvents(
                self::resolvedPath($moduleDeclaration->events, $manifest),
            );
        }

        if ($moduleDeclaration->cli !== null) {
            return $moduleDeclaration->withCli(
                self::resolvedPath($moduleDeclaration->cli, $manifest),
            );
        }

        return $moduleDeclaration;
    }

    /**
     * Resolves one manifest path spelling against the manifest file's own
     * directory -- the one spelling that knows where a relative path is
     * relative to. A rooted spelling rides verbatim; the `./` prefix is
     * the same-directory sugar.
     */
    public static function resolvedPath(string $path, string $manifest): string
    {
        if (str_starts_with($path, './')) {
            $path = mb_substr($path, 2);
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return \dirname($manifest) . '/' . $path;
    }
}
