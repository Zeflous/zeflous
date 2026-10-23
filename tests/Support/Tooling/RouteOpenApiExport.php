<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Export\ExportJson;
use Zef\Framework\Export\OpenApiDocument;
use Zef\Framework\Router\Router;

/**
 * The OpenAPI export's CLI form: the document `bin/zef route:openapi`
 * answers with -- the router's own compiled table rendered through
 * the export family's document fold ({@see OpenApiDocument}), the
 * same rendering the development UI middleware serves, so the
 * committed artifact and the served spec can never drift apart.
 */
final readonly class RouteOpenApiExport
{
    /**
     * The whole declared table as one OpenAPI 3.1 JSON document.
     *
     * @param non-empty-string $title   the API's name, as the caller declares it
     * @param non-empty-string $version the API's own version string
     * @param non-empty-string $scheme  the scheme the server URLs are spelled with
     */
    public static function rendered(Router $router, string $title, string $version, string $scheme): string
    {
        return ExportJson::encode(
            OpenApiDocument::fromRoutes($router->tree()->routes(), $title, $version, $scheme)->toArray(),
        );
    }
}
