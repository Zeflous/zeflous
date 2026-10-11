<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Exception\InvariantViolationException;

/**
 * Radix tree matching versus a linear scan over the same route tables:
 * the engine's structural property, measured on frozen tables both
 * sides share (see `ROADMAP.md`, "Configuration System + DSL + Radix
 * Tree" -- compile once, match in one walk).
 *
 * The claim being evidenced is not "faster than a loop on a small
 * table" -- a plain string comparison is nearly free, and the tree pays
 * its walk on every call. The claim is the walk's flat cost: the tree
 * resolves the same route in the same time whether the table holds 15
 * routes or 300, while the linear scan reads every pattern before the
 * answer (worst case: the sought route is registered last).
 */
final readonly class RouterBench
{
    private const string SOUGHT_PATH = '/section/299';

    private RadixTree $smallTree;

    private RadixTree $largeTree;

    /** @var list<string> */
    private array $smallPatterns;

    /** @var list<string> */
    private array $largePatterns;

    public function __construct()
    {
        $smallRoutes = [
            ['path' => '/', 'method' => ['GET'], 'handler' => 'Root@index'],
            ['path' => '/health', 'method' => ['GET'], 'handler' => 'Health@check'],
            ['path' => '/api/v1/users', 'method' => ['GET'], 'handler' => 'Users@index'],
            ['path' => '/api/v1/users/list', 'method' => ['GET'], 'handler' => 'Users@list'],
            ['path' => '/api/v1/users/export', 'method' => ['GET'], 'handler' => 'Users@export'],
            ['path' => '/api/v1/posts', 'method' => ['GET'], 'handler' => 'Posts@index'],
            ['path' => '/api/v1/posts/list', 'method' => ['GET'], 'handler' => 'Posts@list'],
            ['path' => '/api/v2/users', 'method' => ['GET'], 'handler' => 'Users@indexV2'],
            ['path' => '/api/v2/posts', 'method' => ['GET'], 'handler' => 'Posts@indexV2'],
            ['path' => '/admin/dashboard', 'method' => ['GET'], 'handler' => 'Dashboard@show'],
            ['path' => '/admin/settings', 'method' => ['GET'], 'handler' => 'Settings@show'],
            ['path' => '/admin/users', 'method' => ['GET'], 'handler' => 'Admin\Users@index'],
            ['path' => '/blog', 'method' => ['GET'], 'handler' => 'Blog@index'],
            ['path' => '/blog/latest', 'method' => ['GET'], 'handler' => 'Blog@latest'],
            ['path' => '/blog/archive', 'method' => ['GET'], 'handler' => 'Blog@archive'],
        ];

        $largeRoutes = [];
        for ($section = 0; $section < 300; ++$section) {
            $largeRoutes[] = [
                'path' => \sprintf('/section/%03d', $section),
                'method' => ['GET'],
                'handler' => 'Section@show',
            ];
        }

        $this->smallTree = RadixTree::compile($smallRoutes);
        $this->largeTree = RadixTree::compile($largeRoutes);
        $this->smallPatterns = array_column($smallRoutes, 'path');
        $this->largePatterns = array_column($largeRoutes, 'path');
    }

    #[Revs(10000)]
    public function benchRadixTreeMatchOnASmallTable(): void
    {
        $this->assertResolved($this->smallTree->match('GET', '/blog/archive'), 'Blog@archive');
    }

    #[Revs(10000)]
    public function benchRadixTreeMatchOnALargeTable(): void
    {
        $this->assertResolved($this->largeTree->match('GET', self::SOUGHT_PATH), 'Section@show');
    }

    #[Revs(10000)]
    public function benchLinearScanOnASmallTable(): void
    {
        foreach ($this->smallPatterns as $smallPattern) {
            if ($smallPattern === '/blog/archive') {
                return;
            }
        }

        throw new InvariantViolationException('The linear scan must resolve the route.');
    }

    #[Revs(10000)]
    public function benchLinearScanOnALargeTable(): void
    {
        foreach ($this->largePatterns as $largePattern) {
            if ($largePattern === self::SOUGHT_PATH) {
                return;
            }
        }

        throw new InvariantViolationException('The linear scan must resolve the route.');
    }

    private function assertResolved(MatchResult $matchResult, string $handler): void
    {
        if (!$matchResult->isFound() || $matchResult->route()->handler() !== $handler) {
            throw new InvariantViolationException('The tree walk must resolve the route.');
        }
    }
}
