<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\ContainerBootstrapper;
use Zef\Test\Support\Container\Lazy\EngineFuel;
use Zef\Test\Support\Container\Lazy\HeavyEngine;

/**
 * The lazy lane's boot economics (ROADMAP area 7's proxy half): the boot
 * of a deferring configuration against the plain same set, and the hot
 * resolution paths of both -- the memoised registered hit and the
 * transient's per-resolution answer.
 *
 * Not a victory claim. The honest cost structure these numbers pin: a
 * deferring boot pays the lazy key's parse and the post-install marking
 * walk -- once, at the boot -- while the registered hot path pays
 * nothing by construction: a memoised `get()` never reaches the
 * registry consult the miss path takes, so the two memoised subjects
 * must land in the same band. The transient pair is the only subject
 * that pays the lane's own trade: the eager spelling constructs per
 * resolution, the deferring one creates the native proxy per
 * resolution -- construction now against proxy-creation now plus
 * construction at the first use.
 */
final readonly class LazyBootBench
{
    private Container $deferring;

    private Container $plain;

    public function __construct()
    {
        $this->deferring = $this->booted(deferring: true);
        $this->plain = $this->booted(deferring: false);

        // Prime both memoisations so the memoised subjects measure the
        // hot answer, not the first proxy creation.
        $this->deferring->get('bench.target');
        $this->plain->get('bench.target');
    }

    #[Revs(100)]
    public function benchPlainBoot(): void
    {
        $this->booted(deferring: false);
    }

    #[Revs(100)]
    public function benchDeferringBoot(): void
    {
        $this->booted(deferring: true);
    }

    #[Revs(100)]
    public function benchMemoisedResolutionDeferring(): void
    {
        $this->deferring->get('bench.target');
    }

    #[Revs(100)]
    public function benchMemoisedResolutionPlain(): void
    {
        $this->plain->get('bench.target');
    }

    #[Revs(100)]
    public function benchTransientEagerResolution(): void
    {
        $this->plain->get('bench.transient');
    }

    #[Revs(100)]
    public function benchTransientDeferringResolution(): void
    {
        $this->deferring->get('bench.transient');
    }

    private function booted(bool $deferring): Container
    {
        return new ContainerBootstrapper()
            ->bootstrap(new Config($this->config($deferring)), new Container())
        ;
    }

    /**
     * The definition set both boots share: a leaf, the singleton target,
     * and a transient -- the transient the only declaration whose
     * resolution repeats, so the lane's own trade is the subject it
     * measures.
     *
     * @return array<string, mixed>
     */
    private function config(bool $deferring): array
    {
        return [
            'services' => [
                'bench.fuel' => ['class' => EngineFuel::class],
                'bench.target' => [
                    'class' => HeavyEngine::class,
                    'arguments' => [['service' => 'bench.fuel'], 7],
                    'lazy' => $deferring,
                ],
                'bench.transient' => [
                    'class' => HeavyEngine::class,
                    'arguments' => [['service' => 'bench.fuel'], 3],
                    'lifetime' => 'transient',
                    'lazy' => $deferring,
                ],
            ],
        ];
    }
}
