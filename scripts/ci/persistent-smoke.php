<?php

declare(strict_types=1);

/**
 * Persistent-worker smoke test.
 *
 * RoadRunner (and Swoole/FrankenPHP) workers are long-lived: the kernel is built
 * once and then serves many requests. This smoke test reproduces that shape
 * without requiring the RoadRunner binary — it boots the kernel once and drives
 * a large number of resolutions, asserting that:
 *
 *   1. booting the kernel works;
 *   2. repeated container resolution does not leak memory unboundedly.
 *
 * Usage: php scripts/ci/persistent-smoke.php
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Zef\Framework\Kernel;

const ITERATIONS = 20000;
const MEMORY_BUDGET_BYTES = 2_000_000;

try {
    $kernel = new Kernel();
    $container = $kernel->container();
} catch (Throwable $throwable) {
    fwrite(STDERR, sprintf('Kernel boot FAILED: %s%s', $throwable->getMessage(), PHP_EOL));
    exit(1);
}

$baseline = memory_get_usage();

for ($iteration = 0; $iteration < ITERATIONS; ++$iteration) {
    $container->has('zef.smoke.' . $iteration);
    $kernel->version();
}

$growth = memory_get_usage() - $baseline;

printf(
    'Persistent-worker smoke: %d iterations, memory growth %d bytes (budget %d).%s',
    ITERATIONS,
    $growth,
    MEMORY_BUDGET_BYTES,
    PHP_EOL,
);

if ($growth > MEMORY_BUDGET_BYTES) {
    fwrite(STDERR, 'Persistent-worker smoke FAILED: unbounded memory growth detected.' . PHP_EOL);
    exit(1);
}

echo 'Persistent-worker smoke PASSED.' . PHP_EOL;
