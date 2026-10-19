<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\ContainerBootstrapper;
use Zef\Test\Support\Container\Decorators\FileLogger;
use Zef\Test\Support\Container\Interception\InterceptionLog;
use Zef\Test\Support\Container\Interception\ObservingResolvedListener;
use Zef\Test\Support\Container\Interception\RecordingInterceptor;
use Zef\Test\Support\Container\Interception\RecordingResolvingListener;

/**
 * The interception lane's boot economics (ROADMAP area 9): the boot of
 * an intercepted configuration against the plain same set, and the
 * memoised resolution of an armed container against an unarmed one's.
 *
 * Not a victory claim. The honest cost structure these numbers pin: an
 * interception's price is paid once, at the boot -- the knob's parse,
 * the entries' materialisation (each argument resolved through the
 * plain lane), and the sealed onion -- while the hot path pays nothing
 * by construction: a memoised `get()` never reaches the guarded seam
 * the interception rides, armed or not, so the two resolution subjects
 * must land in the same band. The construction path itself (the
 * cache-miss) is the only lane that pays the listeners and the onion,
 * and a cold construction was never the hot answer.
 */
final readonly class InterceptedBootBench
{
    private Container $armed;

    private Container $plain;

    public function __construct()
    {
        $this->armed = $this->booted(intercepted: true);
        $this->plain = $this->booted(intercepted: false);

        // Prime both memoisations so the resolution subjects measure the
        // hot answer, not the first construction.
        $this->armed->get('bench.target');
        $this->plain->get('bench.target');
    }

    #[Revs(100)]
    public function benchPlainBoot(): void
    {
        $this->booted(intercepted: false);
    }

    #[Revs(100)]
    public function benchInterceptedBoot(): void
    {
        $this->booted(intercepted: true);
    }

    #[Revs(100)]
    public function benchMemoisedResolutionArmed(): void
    {
        $this->armed->get('bench.target');
    }

    #[Revs(100)]
    public function benchMemoisedResolutionPlain(): void
    {
        $this->plain->get('bench.target');
    }

    private function booted(bool $intercepted): Container
    {
        return new ContainerBootstrapper()
            ->bootstrap(new Config($this->config($intercepted)), new Container())
        ;
    }

    /**
     * The definition set both boots share: the observation log, the
     * target, and -- when intercepted -- one interceptor link and both
     * listener kinds over the log.
     *
     * @return array<string, mixed>
     */
    private function config(bool $intercepted): array
    {
        return [
            'services' => [
                'bench.log' => ['class' => InterceptionLog::class],
                'bench.target' => ['class' => FileLogger::class, 'arguments' => ['bench.log']],
            ],
            'interceptors' => $intercepted ? [
                ['class' => RecordingInterceptor::class, 'arguments' => ['bench-link', ['service' => 'bench.log']]],
            ] : [],
            'listeners' => $intercepted ? [
                'resolving' => [
                    ['class' => RecordingResolvingListener::class, 'arguments' => [['service' => 'bench.log']]],
                ],
                'resolved' => [
                    ['class' => ObservingResolvedListener::class, 'arguments' => [['service' => 'bench.log']]],
                ],
            ] : [],
        ];
    }
}
