<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\ContainerBootstrapper;
use Zef\Test\Support\Container\Namespaces\NamespacedService;
use Zef\Test\Support\Container\Namespaces\NamespacedWrapper;

/**
 * The namespace lane's boot economics (ROADMAP area 9): the boot of a
 * governed configuration against the plain same set, and the hot
 * resolution paths of both -- the memoised registered hit and the
 * fallback lane's covered miss.
 *
 * Not a victory claim. The honest cost structure these numbers pin: a
 * governed boot pays the knob's parse, the tree's build and seal, and
 * the scope walk over the graph's edges -- once, at the boot -- while
 * the registered hot path pays nothing by construction: a memoised
 * `get()` never reaches the miss path the fallback lane consults, so
 * the two resolution subjects must land in the same band. The covered
 * miss is the only lane that pays the coverage walk, and a miss was
 * never the hot answer either.
 */
final readonly class NamespacedBootBench
{
    private Container $governed;

    private Container $plain;

    public function __construct()
    {
        $this->governed = $this->booted(governed: true);
        $this->plain = $this->booted(governed: false);

        // The fallback lane the covered-miss subject resolves through.
        $this->plain->namespaceFallbacks()->fallback(
            'bench.ctrl\\',
            static fn (Container $container, string $id): NamespacedService => new NamespacedService($id),
        );

        // Prime both memoisations so the resolution subjects measure the
        // hot answer, not the first construction.
        $this->governed->get('bench.target');
        $this->plain->get('bench.target');
        $this->plain->get('bench.ctrl.Home');
    }

    #[Revs(100)]
    public function benchPlainBoot(): void
    {
        $this->booted(governed: false);
    }

    #[Revs(100)]
    public function benchGovernedBoot(): void
    {
        $this->booted(governed: true);
    }

    #[Revs(100)]
    public function benchMemoisedResolutionGoverned(): void
    {
        $this->governed->get('bench.target');
    }

    #[Revs(100)]
    public function benchMemoisedResolutionPlain(): void
    {
        $this->plain->get('bench.target');
    }

    #[Revs(100)]
    public function benchCoveredMissResolution(): void
    {
        $this->plain->get('bench.ctrl.Home');
    }

    private function booted(bool $governed): Container
    {
        return new ContainerBootstrapper()
            ->bootstrap(new Config($this->config($governed)), new Container())
        ;
    }

    /**
     * The definition set both boots share: a leaf, a consumer, and a
     * governed pair -- plus, when governed, the scope policy that
     * walks their edges.
     *
     * @return array<string, mixed>
     */
    private function config(bool $governed): array
    {
        return [
            'services' => [
                'bench.leaf' => ['class' => NamespacedService::class, 'arguments' => ['leaf']],
                'bench.target' => [
                    'class' => NamespacedWrapper::class,
                    'arguments' => ['target', ['service' => 'bench.leaf']],
                ],
                'bench.governed.inside' => ['class' => NamespacedService::class, 'arguments' => ['inside']],
            ],
            'namespaces' => $governed ? [
                'scopes' => [
                    'bench.governed' => 'internal',
                ],
                'max_cross_scope_refs' => 0,
            ] : ['scopes' => [], 'max_cross_scope_refs' => 0],
        ];
    }
}
