<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerLocator;
use Zef\Framework\ContainerBootstrapper;
use Zef\Test\Support\Container\Namespaces\NamespacedService;
use Zef\Test\Support\Container\Namespaces\NamespacedWrapper;

/**
 * The subset locator's delegation economics (ROADMAP area 10): the
 * memoised answer through the view against the same answer straight
 * from the container -- the boundary check and the delegation are the
 * only lanes the view adds, so the two subjects must land in the same
 * band, and the miss the boundary refuses never reaches a resolution
 * lane at all.
 *
 * Not a victory claim. The honest cost structure these numbers pin:
 * a locator `get()` pays one membership check and one delegated call
 * over the container's own answer -- nothing else, by construction.
 */
final readonly class LocatorBench
{
    private Container $container;

    private ContainerLocator $containerLocator;

    public function __construct()
    {
        $this->container = new ContainerBootstrapper()
            ->bootstrap(new Config($this->config()), new Container())
        ;
        $this->containerLocator = $this->container->locator('bench.target', 'bench.leaf');

        // Prime the memoisation so both subjects measure the hot
        // answer, not the first construction.
        $this->container->get('bench.target');
        $this->containerLocator->get('bench.target');
    }

    #[Revs(100)]
    public function benchMemoisedResolutionDirect(): void
    {
        $this->container->get('bench.target');
    }

    #[Revs(100)]
    public function benchMemoisedResolutionThroughTheLocator(): void
    {
        $this->containerLocator->get('bench.target');
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'services' => [
                'bench.leaf' => ['class' => NamespacedService::class, 'arguments' => ['leaf']],
                'bench.target' => [
                    'class' => NamespacedWrapper::class,
                    'arguments' => ['target', ['service' => 'bench.leaf']],
                ],
            ],
        ];
    }
}
