<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * The APCu extension boundary's function doubles, defined once for the
 * whole suite: the boundary calls the apcu functions unqualified, so
 * PHP resolves them in the boundary's own namespace first -- these
 * doubles shadow the global calls in every environment, extension
 * present or not. Every test that exercises the apcu accounting path
 * (the store boundary's own suite, the construction seam's) shares
 * this one definition, so the answers are order-independent by
 * construction rather than by discovery luck: two suites defining the
 * same namespaced functions with different bodies would answer
 * whichever definition the file discovery happened to run first.
 *
 * The doubles count in `$GLOBALS`, claim add-only, and answer the
 * configurable corrupt values the tests set through `$GLOBALS`, which
 * is the contract the boundary needs: a real increment with its TTL,
 * an add that succeeds once per key, a fetch from the entries (or a
 * corrupt value), and the failure arms.
 *
 * @internal
 */
final class ApcuFunctionDoubles
{
    /**
     * Defines the apcu doubles in the boundary's own namespace (guarded
     * against redeclaration within one process) and resets the state
     * they answer from.
     */
    public static function define(): void
    {
        $GLOBALS['__apcu_inc_calls'] = [];
        $GLOBALS['__apcu_add_calls'] = [];
        $GLOBALS['__apcu_fetch_calls'] = [];
        $GLOBALS['__apcu_counters'] = [];
        $GLOBALS['__apcu_entries'] = [];
        $GLOBALS['__apcu_inc_answer'] = null;
        $GLOBALS['__apcu_fetch_answer'] = null;

        if (\function_exists('Zef\Framework\Http\apcu_inc')) {
            return;
        }

        $doubles = <<<'PHP'
            namespace Zef\Framework\Http;

            function apcu_inc(string $key, int $step = 1, ?bool &$success = null, int $ttl = 0)
            {
                $GLOBALS['__apcu_inc_calls'][] = [$key, $step, $ttl];
                $success = true;

                if (\is_int($GLOBALS['__apcu_inc_answer'])) {
                    return $GLOBALS['__apcu_inc_answer'];
                }

                if ($GLOBALS['__apcu_inc_answer'] !== null) {
                    return $GLOBALS['__apcu_inc_answer'];
                }

                $counters = $GLOBALS['__apcu_counters'];
                $counters[$key] = ($counters[$key] ?? 0) + $step;
                $GLOBALS['__apcu_counters'] = $counters;

                return $counters[$key];
            }

            function apcu_add(string $key, mixed $value, int $ttl = 0): bool
            {
                $GLOBALS['__apcu_add_calls'][] = [$key, $value, $ttl];

                if (\array_key_exists($key, $GLOBALS['__apcu_entries'])) {
                    return false;
                }

                $GLOBALS['__apcu_entries'][$key] = $value;

                return true;
            }

            function apcu_fetch(string $key, ?bool &$success = null): mixed
            {
                $GLOBALS['__apcu_fetch_calls'][] = [$key];

                if ($GLOBALS['__apcu_fetch_answer'] !== null) {
                    $success = true;

                    return $GLOBALS['__apcu_fetch_answer'];
                }

                if (\array_key_exists($key, $GLOBALS['__apcu_entries'])) {
                    $success = true;

                    return $GLOBALS['__apcu_entries'][$key];
                }

                return false;
            }
            PHP;
        eval($doubles);
    }
}
