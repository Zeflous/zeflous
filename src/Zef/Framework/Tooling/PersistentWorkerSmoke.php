<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use stdClass;
use Zef\Framework\Kernel;

/**
 * Persistent-worker smoke test.
 *
 * RoadRunner (and Swoole/FrankenPHP) workers are long-lived: the kernel is built
 * once and then serves many requests. This reproduces that shape without the
 * RoadRunner binary — it boots the kernel once and drives a large number of
 * resolutions, asserting that repeated resolution does not leak memory.
 */
final readonly class PersistentWorkerSmoke
{
    public function __construct(
        private int $iterations,
        private int $memoryBudgetBytes,
    ) {
    }

    public function run(Kernel $kernel): SmokeResult
    {
        $container = $kernel->container();
        $container->set('zef.smoke.service', static fn (): stdClass => new stdClass());

        $baseline = memory_get_usage();
        $first = $container->get('zef.smoke.service');
        $resolved = 0;

        for ($iteration = 0; $iteration < $this->iterations; ++$iteration) {
            if ($container->get('zef.smoke.service') !== $first) {
                throw new ToolingException('The container must memoise the resolved service.');
            }

            ++$resolved;
        }

        return new SmokeResult($resolved, memory_get_usage() - $baseline);
    }

    public function passes(SmokeResult $smokeResult): bool
    {
        return $smokeResult->memoryGrowth <= $this->memoryBudgetBytes;
    }

    public function message(SmokeResult $smokeResult): string
    {
        return \sprintf(
            'Persistent-worker smoke: %d iterations, memory growth %d bytes (budget %d).',
            $smokeResult->iterations,
            $smokeResult->memoryGrowth,
            $this->memoryBudgetBytes,
        );
    }
}
