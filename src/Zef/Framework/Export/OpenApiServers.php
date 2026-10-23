<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\RouteHostLabelKinds;

/**
 * The OpenAPI servers list: the base URLs the document's operations
 * are reachable on -- the subset of the table's host claims that
 * translates without invention.
 *
 * ONLY EXACT HOSTS TRANSLATE. A host claim that is exactly spelled
 * (`admin.example.com`, no whole-label parameters, no wildcards)
 * becomes one server entry, in first-appearance declaration order,
 * under the scheme the caller declared -- because the routing table
 * itself is scheme- and URL-free (the engine judges host labels, not
 * URLs), the scheme is the caller's own declaration, validated to the
 * two spellings that exist. A host claim that carries parameters or
 * wildcards does NOT become a server: the specification demands a
 * server variable's default, the engine owns no default tenant to
 * give, and an invented default is exactly what this family refuses
 * to ship -- the claim stays visible in the operation's own
 * `x-zef-host` extension, verbatim, the router's own spelling.
 *
 * A table without host claims answers an empty list, and the document
 * omits `servers` entirely (the specification's own default, the
 * path-relative `/`, is then the honest statement: the paths are
 * server-relative exactly as the table declares them).
 *
 * @internal consumed through {@see OpenApiDocument}
 */
final readonly class OpenApiServers
{
    /** The schemes a server URL can be spelled with. */
    private const array SCHEMES = ['http', 'https'];

    /**
     * The server entries for the table's exact host claims.
     *
     * @param list<CompiledRoute> $routes the tree's routes, in declaration order
     * @param string              $scheme the caller-declared scheme, `http` or `https`
     *
     * @return list<array{url: non-empty-string}> one Server Object per distinct exact host
     *
     * @throws ExportException when the scheme is not one of the two spellings
     */
    public static function fromRoutes(array $routes, string $scheme): array
    {
        $validated = self::validated($scheme);

        return array_map(
            static fn (string $host): array => ['url' => $validated . '://' . $host],
            self::exactHosts($routes),
        );
    }

    /**
     * @throws ExportException when the scheme is not one of the two spellings
     */
    private static function validated(string $scheme): string
    {
        if (!\in_array($scheme, self::SCHEMES, true)) {
            throw new ExportException(
                \sprintf(
                    'The OpenAPI server scheme must be one of "%s", "%s" given.',
                    implode('", "', self::SCHEMES),
                    $scheme,
                ),
            );
        }

        return $scheme;
    }

    /**
     * The table's distinct exact host claims, in first-appearance
     * declaration order -- the hosts the servers list can spell
     * without inventing anything the engine never declared.
     *
     * @param list<CompiledRoute> $routes
     *
     * @return list<string>
     */
    private static function exactHosts(array $routes): array
    {
        $hosts = [];

        foreach ($routes as $route) {
            $host = $route->host();
            if ($host === null || \in_array($host, $hosts, true)) {
                continue;
            }

            if (!self::isExact($host)) {
                continue;
            }

            $hosts[] = $host;
        }

        return $hosts;
    }

    /**
     * Whether a declared host claim is exactly spelled -- every label
     * a literal, no whole-label parameter and no wildcard: the
     * engine's own label vocabulary ({@see RouteHostLabelKinds})
     * answers the question, never a re-derivation.
     */
    private static function isExact(string $host): bool
    {
        return array_all(explode('.', $host), static fn ($label): bool => !RouteHostLabelKinds::isWildcard($label));
    }
}
