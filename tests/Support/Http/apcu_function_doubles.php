<?php

/**
 * The APCu extension boundary's function doubles, loaded once into the
 * boundary's own namespace for the whole suite: the boundary calls the
 * apcu functions unqualified, so PHP resolves them in that namespace
 * first -- these doubles shadow the global calls in every environment,
 * extension present or not.
 *
 * This is a real, analysed file rather than an eval'd string: the
 * analysers read the exact signatures the suite exercises, the doubles
 * carry their own enforced return types (the corrupt-answer arms answer
 * `mixed`, which is the point -- the boundary's is_int guards exist for
 * exactly those answers), and nothing in the suite needs a dynamic
 * definition. The state they answer from is the typed support class
 * ({@see \Zef\Test\Support\Http\ApcuDoubleState}); the support class
 * that owns the resets ({@see \Zef\Test\Support\Http\ApcuFunctionDoubles})
 * requires this file once per process.
 *
 * The doubles count in the state class, claim add-only, and answer
 * the configurable corrupt values the tests set, which is the contract
 * the boundary needs: a real increment with its TTL, an add that
 * succeeds once per key, a fetch from the entries (or a corrupt value),
 * and the failure arms -- a disabled extension answers neither a
 * successful step (`$incSuccess`) nor a successful claim
 * (`$addSuccess`), exactly as a runtime-disabled APCu does.
 *
 * @internal
 */

declare(strict_types=1);

namespace Zef\Framework\Http;

use Zef\Test\Support\Http\ApcuDoubleState;

/**
 * @param-out bool $success
 */
function apcu_inc(string $key, int $step = 1, ?bool &$success = null, int $ttl = 0): mixed
{
    ApcuDoubleState::$incCalls[] = [$key, $step, $ttl];
    $success = ApcuDoubleState::$incSuccess;

    if (!ApcuDoubleState::$incSuccess) {
        return false;
    }

    if (\is_int(ApcuDoubleState::$incAnswer)) {
        return ApcuDoubleState::$incAnswer;
    }

    if (ApcuDoubleState::$incAnswer !== null) {
        return ApcuDoubleState::$incAnswer;
    }

    ApcuDoubleState::$counters[$key] = (ApcuDoubleState::$counters[$key] ?? 0) + $step;

    return ApcuDoubleState::$counters[$key];
}

function apcu_add(string $key, mixed $value, int $ttl = 0): bool
{
    ApcuDoubleState::$addCalls[] = [$key, $value, $ttl];

    if (!ApcuDoubleState::$addSuccess) {
        return false;
    }

    if (\array_key_exists($key, ApcuDoubleState::$entries)) {
        return false;
    }

    ApcuDoubleState::$entries[$key] = $value;

    return true;
}

/**
 * @param-out bool $success
 */
function apcu_fetch(string $key, ?bool &$success = null): mixed
{
    ApcuDoubleState::$fetchCalls[] = [$key];

    if (ApcuDoubleState::$fetchAnswer !== null) {
        $success = true;

        return ApcuDoubleState::$fetchAnswer;
    }

    if (\array_key_exists($key, ApcuDoubleState::$entries)) {
        $success = true;

        return ApcuDoubleState::$entries[$key];
    }

    $success = false;

    return false;
}
