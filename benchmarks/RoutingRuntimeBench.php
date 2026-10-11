<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RoutingRuntime;
use Zef\Framework\Exception\InvariantViolationException;

/**
 * The routing runtime's economics: the frozen repository is read and the
 * radix tree compiled AT MOST ONCE per instance (see `RoutingRuntime`), so
 * matching through a memoised runtime sits at the tree's flat walk cost,
 * while rebuilding the runtime per request pays the compile every time.
 *
 * This is the roadmap's own claim (`ROADMAP.md`, "Configuration System +
 * DSL + Radix Tree" -- compile once per worker, match flat at runtime);
 * the walk's flatness across table sizes is `RouterBench`'s claim and is
 * not repeated here. Nothing races: the rebuild table is the honest price
 * of skipping the memoisation, not a competitor to it.
 */
final readonly class RoutingRuntimeBench
{
    private Config $smallConfig;

    private Config $largeConfig;

    private RoutingRuntime $smallRuntime;

    private RoutingRuntime $largeRuntime;

    public function __construct()
    {
        $this->smallConfig = $this->config(15);
        $this->largeConfig = $this->config(300);
        $this->smallRuntime = RoutingRuntime::fromConfig($this->smallConfig);
        $this->largeRuntime = RoutingRuntime::fromConfig($this->largeConfig);
    }

    #[Revs(1000)]
    public function benchMatchThroughTheMemoisedRuntime(): void
    {
        $this->assertResolved($this->smallRuntime->match('GET', '/section/7'));
    }

    #[Revs(100)]
    public function benchMatchThroughTheMemoisedLargeRuntime(): void
    {
        $this->assertResolved($this->largeRuntime->match('GET', '/section/299'));
    }

    #[Revs(1000)]
    public function benchRebuildTheRuntimePerMatch(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig($this->smallConfig);
        $this->assertResolved($routingRuntime->match('GET', '/section/7'));
    }

    #[Revs(100)]
    public function benchRebuildTheLargeRuntimePerMatch(): void
    {
        $routingRuntime = RoutingRuntime::fromConfig($this->largeConfig);
        $this->assertResolved($routingRuntime->match('GET', '/section/299'));
    }

    /**
     * @return Config a repository holding a table of numbered section routes
     */
    private function config(int $count): Config
    {
        $routes = [];
        for ($section = 0; $section < $count; ++$section) {
            $routes[] = [
                'path' => '/section/' . $section,
                'method' => ['GET'],
                'handler' => 'Section@show',
            ];
        }

        return new Config(['routes' => $routes]);
    }

    private function assertResolved(MatchResult $matchResult): void
    {
        if (!$matchResult->isFound()) {
            throw new InvariantViolationException('The sought route did not resolve.');
        }
    }
}
