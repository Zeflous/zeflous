<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The parameterised-host claims of a route table: which host patterns
 * were declared per path + version scope and method, so a later
 * equal-specificity pattern that overlaps an earlier one
 * ({@see RouteHostAmbiguities}) is refused as an ambiguity rather than
 * silently resolved by registration order.
 *
 * THE CLAIM SCOPE IS A NESTED TUPLE, NEVER A CONCATENATED KEY (issue
 * #799, finding 3): the first spelling joined the path and the
 * version with a `|` separator, collision-free only because the
 * version grammar happens to refuse the pipe while the path grammar
 * lets a static segment carry one -- the safety borrowed from a
 * sibling grammar the scope never owned. The claims map is the
 * path => version => method walk itself, so no separator grammar
 * can alias two different scopes onto one string, the same
 * discipline the method claims map beside it already spells.
 *
 * @internal
 */
final class RouteHostClaims
{
    /**
     * The claims: path => version => method => the declared host
     * patterns, in declaration order.
     *
     * @var array<string, array<string, array<string, list<array{host: string, pattern: string}>>>>
     */
    private array $claims = [];

    /**
     * Claims a host pattern on one path + version scope and method,
     * refusing an equal-specificity overlap with an earlier claim.
     */
    public function claim(string $path, string $version, string $method, string $host, string $pattern): void
    {
        $conflict = RouteHostAmbiguities::conflicting($host, $this->claims[$path][$version][$method] ?? []);

        if ($conflict !== null) {
            throw new ConfigException(
                \sprintf(
                    'The route host pattern "%s" overlaps "%s" at equal specificity for method "%s" and path "%s".',
                    $host,
                    $conflict['host'],
                    $method,
                    $pattern,
                ),
            );
        }

        $this->claims[$path][$version][$method][] = ['host' => $host, 'pattern' => $pattern];
    }
}
