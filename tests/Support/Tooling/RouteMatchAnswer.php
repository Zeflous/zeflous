<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RouteMatchExplanation;
use Zef\Framework\Router\Router;

/**
 * The route test command's answer: one match, simulated against the
 * booted table, carrying both halves the command renders -- the
 * match's own data ({@see MatchResult}: the found route with its
 * captured parameters, or the 405's allowed methods, or the 406's
 * producible media types) and the staged explanation of why ({@see
 * RouteMatchExplanation}: the failing stage, the narrowing the
 * pipeline performed, the deepest shared prefix and the candidate
 * patterns). The outcome fold is the explanation family's own
 * vocabulary owner ({@see RouteMatchExplanation::outcomeOf()}), so
 * the answer, its text and its JSON all spell the outcomes
 * identically.
 *
 * The pair runs the pipeline twice (the match and its explanation are
 * `match()` and `explain()` over the same immutable tree), which the
 * observability slice pinned as equivalent by test -- the cost is two
 * walks of a tree the command already holds in memory, paid once per
 * command invocation.
 */
final readonly class RouteMatchAnswer
{
    private function __construct(
        public string $outcome,
        public MatchResult $matchResult,
        public RouteMatchExplanation $explanation,
    ) {
    }

    /**
     * Simulates one request against the booted route table.
     *
     * @param null|non-empty-string $host    the request host, when simulating a host-claimed table
     * @param null|non-empty-string $accept  the request's Accept header value, when simulating negotiation
     * @param null|non-empty-string $version the request's API version, when simulating version selection
     */
    public static function answered(
        Router $router,
        string $method,
        string $path,
        ?string $host = null,
        ?string $accept = null,
        ?string $version = null,
    ): self {
        $matchResult = $router->match($method, $path, $host, $accept, $version);

        return new self(
            RouteMatchExplanation::outcomeOf($matchResult),
            $matchResult,
            $router->tree()->explain($method, $path, $host, $accept, $version),
        );
    }
}
