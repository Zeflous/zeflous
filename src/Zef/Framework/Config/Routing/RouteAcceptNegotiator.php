<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * Owns route-produced media type validation and basic HTTP Accept negotiation.
 *
 * @internal
 */
final class RouteAcceptNegotiator
{
    /**
     * @param list<string> $produces
     *
     * @return list<string>
     */
    public static function normalizeProduces(array $produces): array
    {
        $normalized = [];

        foreach ($produces as $mediaType) {
            $mediaType = strtolower(trim($mediaType));
            if (!preg_match('/\A[a-z0-9!#$&^_.+-]+\/[a-z0-9!#$&^_.+-]+\z/D', $mediaType)) {
                throw new ConfigException(sprintf('The route field "produces" contains an invalid media type "%s".', $mediaType));
            }

            if (isset($normalized[$mediaType])) {
                throw new ConfigException(sprintf('The route field "produces" must not repeat media type "%s".', $mediaType));
            }

            $normalized[$mediaType] = $mediaType;
        }

        return array_values($normalized);
    }

    /**
     * An absent Accept header permits every representation declared by a route.
     * The most specific matching range controls quality, so q=0 cannot be
     * overridden by a less-specific wildcard.
     *
     * @param list<string> $produces
     */
    public static function accepts(?string $accept, array $produces): bool
    {
        if ($produces === [] || $accept === null || trim($accept) === '') {
            return true;
        }

        $ranges = [];
        foreach (explode(',', $accept) as $entry) {
            $parts = array_map('trim', explode(';', $entry));
            $mediaRange = strtolower(array_shift($parts) ?? '');
            if (!preg_match('/\A(\*|[a-z0-9!#$&^_.+-]+)\/(\*|[a-z0-9!#$&^_.+-]+)\z/D', $mediaRange, $matches)) {
                continue;
            }

            if ($matches[1] === '*' && $matches[2] !== '*') {
                continue;
            }

            $quality = 1.0;
            $valid = true;
            foreach ($parts as $parameter) {
                if (!str_starts_with(strtolower($parameter), 'q=')) {
                    continue;
                }

                $rawQuality = substr($parameter, 2);
                if (!preg_match('/\A(?:0(?:\.\d{0,3})?|1(?:\.0{0,3})?)\z/D', $rawQuality)) {
                    $valid = false;
                    break;
                }

                $quality = (float) $rawQuality;
            }

            if (!$valid) {
                continue;
            }

            $ranges[] = [
                'type' => $matches[1],
                'subtype' => $matches[2],
                'quality' => $quality,
                'specificity' => ($matches[1] === '*' ? 0 : 1) + ($matches[2] === '*' ? 0 : 1),
            ];
        }

        foreach ($produces as $mediaType) {
            [$type, $subtype] = explode('/', $mediaType, 2);
            $bestSpecificity = -1;
            $bestQuality = 0.0;

            foreach ($ranges as $range) {
                if (
                    ($range['type'] !== '*' && $range['type'] !== $type)
                    || ($range['subtype'] !== '*' && $range['subtype'] !== $subtype)
                ) {
                    continue;
                }

                if ($range['specificity'] > $bestSpecificity) {
                    $bestSpecificity = $range['specificity'];
                    $bestQuality = $range['quality'];
                } elseif ($range['specificity'] === $bestSpecificity) {
                    $bestQuality = max($bestQuality, $range['quality']);
                }
            }

            if ($bestSpecificity >= 0 && $bestQuality > 0.0) {
                return true;
            }
        }

        return false;
    }
}
