<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Export\ExportJson;
use Zef\Framework\Export\PostmanCollection;
use Zef\Framework\Router\Router;

/**
 * The Postman export's CLI form: the collection `bin/zef route:postman`
 * answers with -- the router's own compiled table rendered through the
 * export family's collection fold ({@see PostmanCollection}), the
 * deterministic id and all, ready to import as-is.
 */
final readonly class RoutePostmanExport
{
    /**
     * The whole declared table as one Postman Collection v2.1.0 JSON document.
     *
     * @param non-empty-string $name the collection's name, as the caller declares it
     */
    public static function rendered(Router $router, string $name): string
    {
        return ExportJson::encode(
            PostmanCollection::fromRoutes($router->tree()->routes(), $name)->toArray(),
        );
    }
}
