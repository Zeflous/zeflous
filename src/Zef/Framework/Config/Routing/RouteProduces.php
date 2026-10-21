<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * Compile-time validation and normalisation of a route's declared
 * "produces" media types: lower-case, trimmed, grammar-checked and
 * duplicate-free before any route exists.
 *
 * @internal
 */
final class RouteProduces
{
    /**
     * @param list<string> $produces
     *
     * @return list<string>
     */
    public static function normalize(array $produces): array
    {
        $normalized = [];

        foreach ($produces as $produce) {
            $produce = mb_strtolower(mb_trim($produce));
            if (preg_match('/\A[a-z0-9!#$&^_.+-]+\/[a-z0-9!#$&^_.+-]+\z/D', $produce) !== 1) {
                throw new ConfigException(\sprintf(
                    'The route field "produces" contains an invalid media type "%s".',
                    $produce,
                ));
            }

            if (isset($normalized[$produce])) {
                throw new ConfigException(\sprintf(
                    'The route field "produces" must not repeat media type "%s".',
                    $produce,
                ));
            }

            $normalized[$produce] = $produce;
        }

        return array_values($normalized);
    }
}
