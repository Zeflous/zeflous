<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteDsl;
use Zef\Framework\Exception\InvariantViolationException;

/**
 * The route DSL's build cost: declaring a route table fluently, then
 * compiling it into the tree.
 *
 * The claim being evidenced is the roadmap's own economics (see
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree" -- the DSL
 * runs once, at build/cache time): the declaration work is linear in
 * the number of routes (every declaration carries its validation), and
 * it is paid once -- the runtime match on the compiled tree stays flat
 * whatever the table size (that flatness is `RouterBench`'s claim, not
 * repeated here). Nothing here races a hand-written array literal; a
 * fluent declaration buys validation and fail-closed errors, and its
 * price is what this table measures.
 */
final readonly class RouteDslBench
{
    #[Revs(1000)]
    public function benchDefineASmallTable(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            self::declare($routeDsl, 15);
        });

        $this->assertDeclared(15, $routes);
    }

    #[Revs(100)]
    public function benchDefineALargeTable(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            self::declare($routeDsl, 300);
        });

        $this->assertDeclared(300, $routes);
    }

    #[Revs(100)]
    public function benchDefineAndCompileALargeTable(): void
    {
        $routes = RouteDsl::define(static function (RouteDsl $routeDsl): void {
            self::declare($routeDsl, 300);
        });
        $radixTree = RadixTree::compile($routes);

        $this->assertResolved($radixTree->match('GET', '/section/299'));
    }

    /**
     * Declares a table of numbered section routes.
     */
    private static function declare(RouteDsl $routeDsl, int $count): void
    {
        for ($section = 0; $section < $count; ++$section) {
            $routeDsl->get('/section/' . $section, 'Section@show');
        }
    }

    /**
     * @param list<array<string, mixed>> $routes
     */
    private function assertDeclared(int $expected, array $routes): void
    {
        if (\count($routes) !== $expected) {
            throw new InvariantViolationException(
                \sprintf('Expected %d declared routes, got %d.', $expected, \count($routes)),
            );
        }
    }

    private function assertResolved(MatchResult $matchResult): void
    {
        if (!$matchResult->isFound()) {
            throw new InvariantViolationException('The sought route did not resolve.');
        }
    }
}
