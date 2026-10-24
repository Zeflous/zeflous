<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The operation's `x-zef-*` extension family: the fields the table
 * knows and the specification has no field for -- the handler, the
 * host claim, the API version, the route middleware -- carried
 * verbatim, the engine's own spellings, never a translation.
 *
 * The fold exists so {@see OpenApiOperation} can stay a
 * single-question class under the architecture gate's difficulty
 * ceiling: the extensions are the table's own one question (what
 * does this route carry that the document's fields don't spell?),
 * and the operation's answer to it is a merge, nothing more.
 *
 * @internal consumed through {@see OpenApiOperation}
 */
final readonly class OpenApiOperationExtensions
{
    /**
     * The extension fields one route's operation carries.
     *
     * @param CompiledRoute $compiledRoute the route entry being rendered
     *
     * @return array<string, mixed> the `x-zef-handler` field, plus the host, version and middleware the table declares
     */
    public static function for(CompiledRoute $compiledRoute): array
    {
        $extensions = ['x-zef-handler' => $compiledRoute->handler()];

        if ($compiledRoute->host() !== null) {
            $extensions['x-zef-host'] = $compiledRoute->host();
        }

        if ($compiledRoute->version() !== null) {
            $extensions['x-zef-version'] = $compiledRoute->version();
        }

        if ($compiledRoute->middleware() !== []) {
            $extensions['x-zef-middleware'] = $compiledRoute->middleware();
        }

        return $extensions;
    }
}
