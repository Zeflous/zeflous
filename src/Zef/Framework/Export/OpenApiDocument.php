<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use stdClass;
use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The OpenAPI 3.1 document itself: the whole route table as one
 * specification document -- the roadmap's ecosystem export, the
 * artifact every OpenAPI-consuming tool (documentation UIs, code
 * generators, contract testers, Postman importers) reads.
 *
 * The document is a RENDERING of the engine's own compiled table
 * ({@see \Zef\Framework\Config\Routing\RadixTree::routes()}, the
 * read-side seam the CLI inspector landed), never a parallel
 * compilation: the paths, methods, parameters, constraints, hosts,
 * versions, producible types and deprecations are the table's own
 * values, and the document adds exactly the two things the
 * specification demands that the table does not own -- the API's
 * identity ({@see OpenApiInfo}) and the scheme its server URLs are
 * spelled with ({@see OpenApiServers}).
 *
 * AN EMPTY TABLE ANSWERS AN EMPTY DOCUMENT -- `openapi` and `info`
 * alone, the specification's own minimum (it demands one of
 * paths/components/webhooks, and a table with no routes declares none
 * of them): the honest empty, the same zero-route spelling the CLI
 * inspector renders, never a synthetic health-check path the table
 * never declared.
 *
 * @api
 */
final readonly class OpenApiDocument
{
    /** The OpenAPI specification version this family renders. */
    public const string OPENAPI_VERSION = '3.1.0';

    /** @param list<CompiledRoute> $routes */
    private function __construct(
        private array $routes,
        private OpenApiInfo $openApiInfo,
        private string $scheme,
    ) {
    }

    /**
     * @param list<CompiledRoute> $routes  the tree's routes, in declaration order
     * @param string              $title   the API's name
     * @param string              $version the API's own version string
     * @param string              $scheme  the scheme the server URLs are spelled with, `http` or `https`
     */
    public static function fromRoutes(array $routes, string $title, string $version, string $scheme): self
    {
        return new self(
            $routes,
            OpenApiInfo::fromFields($title, $version),
            $scheme,
        );
    }

    /**
     * The document as its JSON form carries it.
     *
     * @return array<string, mixed> the OpenAPI Object: `openapi`, `info`,
     *                              `servers` (when the table claims hosts), `paths`
     *                              (always -- the specification demands one of
     *                              paths/components/webhooks be present, and an empty
     *                              table is the honest `paths: {}`)
     *
     * @throws ExportException when any route's enrichment declaration refuses
     */
    public function toArray(): array
    {
        $document = [
            'openapi' => self::OPENAPI_VERSION,
            'info' => $this->openApiInfo->toArray(),
        ];

        $servers = OpenApiServers::fromRoutes($this->routes, $this->scheme);
        if ($servers !== []) {
            $document['servers'] = $servers;
        }

        $paths = OpenApiPaths::grouped($this->routes);
        $document['paths'] = $paths === [] ? new stdClass() : $paths;

        return $document;
    }
}
