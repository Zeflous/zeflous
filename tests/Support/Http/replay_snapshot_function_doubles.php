<?php

/**
 * The replay snapshot boundary's function doubles, loaded once into the
 * boundary's own namespace for the whole suite: the writer and the decoder
 * call chmod, flock, fwrite and json_encode unqualified, so PHP resolves
 * them in the namespace first -- these doubles shadow the global calls,
 * answer the engine's own result by default, and expose the failure arms
 * and the short-write policy the tests arm explicitly.
 *
 * This is a real, analysed file rather than an eval'd string, for the same
 * reasons the APCu boundary's doubles are (see
 * {@see apcu_function_doubles.php}): the analysers read the exact
 * signatures the suite exercises, and the state they answer from is the
 * typed support class ({@see \Zef\Test\Support\Http\ReplaySnapshotDoubleState}).
 *
 * Passthrough is the default for every double, so the upload and stream
 * transfer paths that share the namespace's fwrite calls keep their
 * behaviour; only a test that arms a failure or a byte budget sees a
 * double's own answer.
 *
 * @internal
 */

declare(strict_types=1);

namespace Zef\Framework\Http;

use Zef\Test\Support\Http\ReplaySnapshotDoubleState;

use function chmod as engine_chmod;
use function error_log as engine_error_log;
use function flock as engine_flock;
use function fwrite as engine_fwrite;
use function json_encode as engine_json_encode;
use function unlink as engine_unlink;

function chmod(string $filename, int $permissions): bool
{
    if (ReplaySnapshotDoubleState::$chmodAnswer !== null) {
        $result = ReplaySnapshotDoubleState::$chmodAnswer;
    } else {
        $result = engine_chmod($filename, $permissions);
    }

    if (ReplaySnapshotDoubleState::$record) {
        ReplaySnapshotDoubleState::$chmodCalls[] = [$filename, $permissions, $result];
    }

    return $result;
}

/**
 * @param resource  $stream
 * @param int<0, 7> $operation
 */
function flock($stream, int $operation, ?int &$would_block = null): bool
{
    if (ReplaySnapshotDoubleState::$flockAnswer !== null) {
        $result = ReplaySnapshotDoubleState::$flockAnswer;
    } else {
        $result = engine_flock($stream, $operation, $would_block);
    }

    if (ReplaySnapshotDoubleState::$record) {
        ReplaySnapshotDoubleState::$flockCalls[] = [$operation, $result];
    }

    return $result;
}

/**
 * @param resource         $stream
 * @param null|int<0, max> $length
 */
function fwrite($stream, string $data, ?int $length = null): false|int
{
    if (ReplaySnapshotDoubleState::$fwriteAnswers !== []) {
        $answered = array_shift(ReplaySnapshotDoubleState::$fwriteAnswers);
    } elseif (ReplaySnapshotDoubleState::$fwriteChunk !== null) {
        $budget = min(ReplaySnapshotDoubleState::$fwriteChunk, mb_strlen($data, '8bit'));
        $answered = engine_fwrite($stream, mb_substr($data, 0, $budget, '8bit'));
    } else {
        $answered = engine_fwrite($stream, $data, $length);
    }

    if (ReplaySnapshotDoubleState::$record) {
        ReplaySnapshotDoubleState::$fwriteCalls[] = [$data, $answered];
    }

    return $answered;
}

/**
 * The retention walk's removal, shadowed with the boundary's own arms: the
 * queued answers are consumed oldest-call-first, so a test that arms a
 * refusal watches the walk say so on the error_log double and keep walking
 * past the stuck entry; every unarmed call answers the engine's own unlink.
 *
 * @param null|resource $context
 */
function unlink(string $filename, $context = null): bool
{
    if (ReplaySnapshotDoubleState::$unlinkAnswers !== []) {
        $result = array_shift(ReplaySnapshotDoubleState::$unlinkAnswers);
    } else {
        $result = engine_unlink($filename, $context);
    }

    if (ReplaySnapshotDoubleState::$record) {
        ReplaySnapshotDoubleState::$unlinkCalls[] = [$filename, $result];
    }

    return $result;
}

/**
 * The boundary's SAPI log seam, shadowed for recording alone: while a test
 * holds the record arm, the message is captured for the assertions and
 * answered without touching the engine's own sink -- an armed test must not
 * write to the runner's STDERR either (the mutation lane's initial run died
 * on exactly that emission). Unarmed, the double is pure passthrough: every
 * other suite and the production process keep the engine's own behaviour.
 *
 * @param 0|1|3|4 $message_type the engine's own message-type axis
 */
function error_log(
    string $message,
    int $message_type = 0,
    ?string $destination = null,
    ?string $additional_headers = null,
): bool {
    if (ReplaySnapshotDoubleState::$record) {
        ReplaySnapshotDoubleState::$errorLogMessages[] = $message;

        return true;
    }

    return engine_error_log($message, $message_type, $destination, $additional_headers);
}

/**
 * The literals below are the boundary's two throw-mode flag
 * spellings: 4194304 is JSON_THROW_ON_ERROR alone and 6291456 that
 * flag together with JSON_INVALID_UTF8_SUBSTITUTE. PHPDoc's type
 * grammar cannot spell the bitwise combination, so the two reachable
 * spellings stand as the literals themselves -- every caller that
 * carries the throwing flag sees the narrowed string the global's own
 * analyser narrowing would answer, while every other flag set (the
 * renderer's pretty-printed one, the flagless default) keeps the
 * honest string|false the engine can still return for them.
 *
 * @param int<1, max> $depth
 *
 * @phpstan-return ($flags is 4194304|6291456 ? string : string|false)
 *
 * @psalm-return ($flags is 4194304|6291456 ? string : string|false)
 */
function json_encode(mixed $value, int $flags = 0, int $depth = 512): false|string
{
    $result = engine_json_encode($value, $flags, $depth);

    if (ReplaySnapshotDoubleState::$record) {
        ReplaySnapshotDoubleState::$jsonEncodeCalls[] = [$flags, $depth, $result];
    }

    return $result;
}
