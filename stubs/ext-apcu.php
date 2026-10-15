<?php

declare(strict_types=1);

/*
 * Static-analysis stub for the optional APCu extension (`ext-apcu`,
 * suggested in composer.json): the three atomic primitives the shared
 * rate-limit store's boundary calls, in the global namespace the
 * production code falls back to. The stub keeps phpstan and psalm honest
 * in environments that do not carry the extension -- the boundary class
 * itself is verified against namespaced function doubles the test suite
 * defines (which shadow the global calls in every environment, extension
 * present or not).
 */

namespace {

    /**
     * @param-out bool $success
     */
    function apcu_inc(string $key, int $step = 1, ?bool &$success = null, int $ttl = 0): int|false
    {
        return false;
    }

    function apcu_add(string $key, mixed $value, int $ttl = 0): bool
    {
        return false;
    }

    /**
     * @param-out bool $success
     */
    function apcu_fetch(string $key, ?bool &$success = null): mixed
    {
        return false;
    }
}
