<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use stdClass;
use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The operation's Responses Object: the one response the
 * specification requires every operation to carry, and the honest
 * content map the table's own producible media types spell.
 *
 * The response's description tells the reader the truth: the table
 * declares the method, not the response's schema -- and the producible
 * media types ({@see CompiledRoute::produces()}) ride as the content
 * map, each media type an honest empty object (the table does not know
 * the schema, and the schema field is absent rather than invented). A
 * route that produces nothing answers the required response without a
 * content map at all.
 *
 * @internal consumed through {@see OpenApiOperation}
 */
final readonly class OpenApiResponses
{
    /** The honest one-response description every operation carries. */
    private const string RESPONSE_DESCRIPTION = 'The routing table declares this method on the route;'
        . ' response schemas are not declared by the table.';

    /**
     * The Responses Object for one route.
     *
     * @param CompiledRoute $compiledRoute the route entry being rendered
     *
     * @return array<int|string, mixed> the required one response, its content the route's producible
     *                                  types (the `200` key is the status code the JSON form quotes)
     */
    public static function for(CompiledRoute $compiledRoute): array
    {
        $response = ['description' => self::RESPONSE_DESCRIPTION];

        $content = [];
        foreach ($compiledRoute->produces() as $mediaType) {
            $content[$mediaType] = new stdClass();
        }

        if ($content !== []) {
            $response['content'] = $content;
        }

        return ['200' => $response];
    }
}
