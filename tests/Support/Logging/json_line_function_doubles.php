<?php

/**
 * The JSON log line boundary's function doubles, loaded once into the
 * boundary's own namespace for the whole suite: the writer and the
 * record writer call flock and fwrite unqualified, so PHP resolves them
 * in the namespace first -- these doubles shadow the global calls,
 * answer the engine's own result by default, and expose the failure arms
 * and the short-write policy the tests arm explicitly.
 *
 * This is a real, analysed file rather than an eval'd string, for the
 * same reasons the APCu boundary's doubles are (see
 * {@see apcu_function_doubles.php}): the analysers read the exact
 * signatures the suite exercises, and the state they answer from is the
 * typed support class ({@see \Zef\Test\Support\Logging\JsonLineDoubleState}).
 *
 * The doubles reach the global functions through alias imports alone: a
 * leading backslash would be erased by the formatter's native-invocation
 * rule (neither function is compiler-optimised, and the rule's strict
 * mode strips the separator from every function outside its include
 * set), while an unqualified call resolves to the double itself -- the
 * very shadow the boundary needs -- and recurses. The byte budget walks
 * isset() offsets, the boundary's own documented idiom (see
 * {@see UriEncoder}), so the multibyte-safe fixer's strlen/substr
 * rewrite cannot turn the budget into character arithmetic.
 *
 * Passthrough is the default for every double, so the append and record
 * paths that share the namespace's calls keep their behaviour; only a
 * test that arms a failure or a byte budget sees a double's own answer.
 *
 * @internal
 */

declare(strict_types=1);

namespace Zef\Framework\Logging;

use Zef\Test\Support\Logging\JsonLineDoubleState;

use function flock as engine_flock;
use function fwrite as engine_fwrite;

/**
 * The suite exercises the boundary's two-argument calls alone -- the
 * would-be third reference argument never appears at a call site, so the
 * double carries the two-argument shape the suite actually resolves.
 *
 * @param resource  $stream
 * @param int<0, 7> $operation
 */
function flock($stream, int $operation): bool
{
    if (JsonLineDoubleState::$flockAnswer !== null) {
        $result = JsonLineDoubleState::$flockAnswer;
    } else {
        $result = engine_flock($stream, $operation);
    }

    if (JsonLineDoubleState::$record) {
        JsonLineDoubleState::$flockCalls[] = [$operation, $result];
    }

    return $result;
}

/**
 * The short-write policy is byte-wise through isset() offsets -- the
 * boundary's own documented idiom -- deliberately not strlen()/substr()
 * arithmetic, which the coding standard's multibyte-safe fixer would
 * silently turn into character arithmetic: the byte budget must stop
 * mid-character inside a multibyte line for the character-offset walk
 * the record writer's loop contract pins.
 *
 * @param resource         $stream
 * @param null|int<0, max> $length
 */
function fwrite($stream, string $data, ?int $length = null): false|int
{
    if (JsonLineDoubleState::$fwriteAnswers !== []) {
        $answered = array_shift(JsonLineDoubleState::$fwriteAnswers);
    } elseif (JsonLineDoubleState::$fwriteChunk !== null) {
        $budget = '';
        $index = 0;
        while ($index < JsonLineDoubleState::$fwriteChunk && isset($data[$index])) {
            $budget .= $data[$index];
            ++$index;
        }

        $answered = engine_fwrite($stream, $budget);
    } else {
        $answered = engine_fwrite($stream, $data, $length);
    }

    if (JsonLineDoubleState::$record) {
        JsonLineDoubleState::$fwriteCalls[] = [$data, $answered];
    }

    return $answered;
}
