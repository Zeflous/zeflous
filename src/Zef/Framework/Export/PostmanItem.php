<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * One Postman collection item: the route-and-method pair as one
 * request a client can fire -- Postman's unit of "an endpoint I can
 * try" ({@see PostmanCollection} carries the list).
 *
 * The translation is the table's own truth in Postman's own idioms:
 *
 * - the item's name is the route's reverse-routing name, or the path
 *   pattern verbatim when the route is unnamed (an unnamed route is
 *   not an error the export repairs -- the pattern is the honest
 *   identifier the table itself offers);
 * - the request's URL spells the pattern in Postman's path-variable
 *   idiom: `{id}` becomes `:id` in the raw URL and the path segment
 *   list, and the variables carry the parameter names the pattern
 *   declares -- the request is ready to fill, with no value guessed;
 * - the request's headers are the ones the table's own negotiation
 *   seams read: `Accept` carries the producible media types (the
 *   engine's Accept negotiation answers exactly these), and
 *   `Accept-Version` carries the route's declared API version (the
 *   engine's version selection reads this header first) -- a request
 *   the collection fires exercises the same grammars a live client
 *   does.
 *
 * @internal consumed through {@see PostmanCollection}
 */
final readonly class PostmanItem
{
    /**
     * The item object for one route at one of its methods.
     *
     * @param CompiledRoute $compiledRoute the route entry being rendered
     * @param string        $method        one of the route's upper-cased methods
     *
     * @return array<string, mixed> the Postman Item Object
     */
    public static function fromRoute(CompiledRoute $compiledRoute, string $method): array
    {
        return [
            'name' => $compiledRoute->name() ?? $compiledRoute->path()->pattern(),
            'request' => [
                'method' => $method,
                'header' => self::headers($compiledRoute),
                'url' => self::url($compiledRoute),
            ],
        ];
    }

    /**
     * @return list<array{key: non-empty-string, value: string}> the headers the table's negotiation seams read
     */
    private static function headers(CompiledRoute $compiledRoute): array
    {
        $headers = [];

        if ($compiledRoute->produces() !== []) {
            $headers[] = [
                'key' => 'Accept',
                'value' => implode(', ', $compiledRoute->produces()),
            ];
        }

        $version = $compiledRoute->version();

        if ($version !== null) {
            $headers[] = [
                'key' => 'Accept-Version',
                'value' => $version,
            ];
        }

        return $headers;
    }

    /**
     * @return array<string, mixed> the URL Object: `raw` (the raw URL), `path`
     *                              (the segment list, parameters as `:name`),
     *                              `variable` (the path variables, ready to fill)
     */
    private static function url(CompiledRoute $compiledRoute): array
    {
        $raw = '';
        $path = [];
        $variables = [];

        foreach ($compiledRoute->path()->segments() as $pathSegment) {
            if ($pathSegment->isParameter()) {
                /** @var non-empty-string $name */
                $name = $pathSegment->value();
                $raw .= '/:' . $name;
                $path[] = ':' . $name;
                $variables[] = ['key' => $name, 'value' => ''];

                continue;
            }

            $raw .= '/' . $pathSegment->value();
            $path[] = $pathSegment->value();
        }

        $rawUrl = $raw === '' ? '/' : $raw;

        return [
            'raw' => $rawUrl,
            'path' => $path,
            'variable' => $variables,
        ];
    }
}
