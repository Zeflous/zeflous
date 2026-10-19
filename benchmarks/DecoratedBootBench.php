<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\ContainerBootstrapper;
use Zef\Test\Support\Container\Decorators\FileLogger;
use Zef\Test\Support\Container\Decorators\LoggerDecorators;
use Zef\Test\Support\Container\Decorators\PrefixedLogger;

/**
 * The decoration lane's boot economics (ROADMAP area 7): the boot of a
 * decorated definition set against the undecorated same set, and the
 * memoised resolution of the wrapped chain against a plain service's.
 *
 * Not a victory claim. The honest cost structure these numbers pin: a
 * decoration's price is paid once, at the boot -- the entry parse, the
 * surgery, and the wrapper definitions the install loop compiles like
 * any other declaration -- and it grows with the chain, so on this
 * two-service fixture, where a two-decorator chain adds two definitions
 * to a set of two, the decorated boot lands well above the undecorated
 * band; on a real host's set the same absolute delta drowns in the
 * boot's own walk. The hot path pays nothing by construction: a
 * memoised `get()` on the wrapped chain is an ordinary service
 * reference resolving ordinary definitions, the same lane any
 * constructor dependency rides, so the two resolution subjects must
 * land in the same band -- the decoration is a declaration-time
 * concern, never a second runtime.
 */
final readonly class DecoratedBootBench
{
    private Container $container;

    public function __construct()
    {
        $this->container = new ContainerBootstrapper()
            ->bootstrap(new Config($this->config(decorated: true)), new Container())
        ;

        // Prime the memoisation so the resolution subject measures the
        // hot answer, not the first chain construction.
        $this->container->get('bench.logger');
    }

    #[Revs(100)]
    public function benchUndecoratedBoot(): void
    {
        new ContainerBootstrapper()
            ->bootstrap(new Config($this->config(decorated: false)), new Container())
        ;
    }

    #[Revs(100)]
    public function benchDecoratedBoot(): void
    {
        new ContainerBootstrapper()
            ->bootstrap(new Config($this->config(decorated: true)), new Container())
        ;
    }

    #[Revs(100)]
    public function benchMemoisedResolutionOfTheWrappedChain(): void
    {
        $this->container->get('bench.logger');
    }

    #[Revs(100)]
    public function benchMemoisedResolutionOfAPlainService(): void
    {
        $this->container->get('bench.path');
    }

    /**
     * The definition set both boots share: a value, the file logger over
     * it, and -- when decorated -- the trace-outside-timing chain the
     * lane rewrites the logger into.
     *
     * @return array<string, mixed>
     */
    private function config(bool $decorated): array
    {
        return [
            'services' => [
                'bench.path' => ['value' => 'bench.log'],
                'bench.logger' => [
                    'class' => FileLogger::class,
                    'arguments' => [['service' => 'bench.path']],
                    'tags' => ['bench.logger'],
                ],
            ],
            'decorators' => $decorated ? [
                'bench.logger' => [
                    [
                        'class' => PrefixedLogger::class,
                        'arguments' => [['service' => '@inner'], 'trace'],
                    ],
                    [
                        'factory' => LoggerDecorators::class . '::timing',
                        'arguments' => [['service' => '@inner']],
                    ],
                ],
            ] : [],
        ];
    }
}
