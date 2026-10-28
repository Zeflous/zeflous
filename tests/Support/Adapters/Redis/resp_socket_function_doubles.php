<?php

/**
 * The RESP socket boundary's function doubles, loaded once into the
 * wire family's own namespace for the whole suite: the socket fold
 * calls fsockopen, fwrite, fread and stream_set_timeout unqualified,
 * so PHP resolves them in the namespace first -- these doubles
 * shadow the global calls, answer the engine's own result by
 * default, and expose the arms the tests arm explicitly (a refused
 * connection, a refused or partial write, a dead stream, scripted
 * reply chunks), without the engine's own failure answers otherwise
 * emitting warnings.
 *
 * The read double honours the requested length the engine itself
 * honours: a chunk longer than the request is split, the remainder
 * staying queued for the next read -- so a connection that asked for
 * the wrong length is observable, and a chunked reply reassembles
 * through the buffer the way a real socket delivers it. The write
 * double's int answer is the partial-write arm: the byte count the
 * engine let through, the write loop's own discipline to keep
 * writing past.
 *
 * This is a real, analysed file rather than an eval'd string, for
 * the same reasons the file delivery boundary's doubles are (see
 * {@see tests/Support/Adapters/Event/delivery_file_function_doubles.php}):
 * the state they answer from is the typed support class
 * {@see \Zef\Test\Support\Adapters\Redis\RespSocketDoubleState}.
 *
 * Passthrough is the default for every double, so every other suite
 * -- the integration tier included -- keeps its real behaviour; only
 * a test that arms an answer sees a double's own. The connect
 * passthrough carries the diagnostics-suppression operator the
 * loopback probe precedent owns ({@see \Zef\Test\Support\Telemetry\OtlpMockServer}):
 * a refused connection is already spelled completely by the errno
 * and error string the engine answers by reference -- the warning
 * it also emits is the same fact twice, noise the suite never asked
 * to hear.
 *
 * @internal
 */

declare(strict_types=1);

namespace Zef\Framework\Adapters\Redis;

use Zef\Test\Support\Adapters\Redis\RespSocketDoubleState;

use function fread as engine_fread;
use function fsockopen as engine_fsockopen;
use function fwrite as engine_fwrite;
use function stream_set_timeout as engine_stream_set_timeout;

/**
 * The connect boundary: the armed resource the connection will hold,
 * the refused arm with its own cause, or the engine's own answer.
 *
 * @param int    $errorCode   the engine's own refusal code, answered by reference
 * @param string $errorString the engine's own refusal phrase, answered by reference
 *
 * @return false|resource the stream the connection asked for, or the refusal
 */
function fsockopen(
    string $hostname,
    int $port,
    int &$errorCode,
    string &$errorString,
    ?float $timeout = null,
): mixed {
    if (RespSocketDoubleState::$connectAnswer !== null) {
        if (RespSocketDoubleState::$connectAnswer === false) {
            $errorCode = RespSocketDoubleState::$connectErrorCode;
            $errorString = RespSocketDoubleState::$connectErrorString;

            return false;
        }

        return RespSocketDoubleState::$connectAnswer;
    }

    return @engine_fsockopen($hostname, $port, $errorCode, $errorString, $timeout);
}

/**
 * The write boundary: the refused arm, the partial byte count the
 * write loop must keep writing past, or the engine's own answer.
 *
 * @param resource $stream the stream the engine's own call accepts
 * @param null|int $length the byte cap the engine's own call accepts
 *
 * @return false|int the bytes the armed answer lets through, or the engine's own
 */
function fwrite($stream, string $data, ?int $length = null): false|int
{
    $carried = $length === null ? $data : mb_substr($data, 0, $length, '8bit');

    if (RespSocketDoubleState::$writeAnswer === false) {
        return false;
    }

    if (\is_int(RespSocketDoubleState::$writeAnswer)) {
        RespSocketDoubleState::$writtenCommands[] = mb_substr(
            $carried,
            0,
            RespSocketDoubleState::$writeAnswer,
            '8bit',
        );

        return min(RespSocketDoubleState::$writeAnswer, mb_strlen($carried, '8bit'));
    }

    RespSocketDoubleState::$writtenCommands[] = $carried;

    return engine_fwrite($stream, $carried);
}

/**
 * The read boundary: the scripted chunk (honouring the requested
 * length the engine itself honours), the dead-stream arm, the
 * exhausted queue's own end, or the engine's own answer.
 *
 * @param resource $stream the stream the engine's own call accepts
 *
 * @return false|string the chunk the wire will buffer, or the refusal
 */
function fread($stream, int $length): false|string
{
    if ($length < 1) {
        return '';
    }

    if (RespSocketDoubleState::$readChunks === null) {
        return engine_fread($stream, $length);
    }

    $chunks = RespSocketDoubleState::$readChunks;

    if (($chunks[0] ?? '') === false) {
        return false;
    }

    $chunk = $chunks[0] ?? '';

    if ($chunk === '') {
        return '';
    }

    if (mb_strlen($chunk, '8bit') > $length) {
        RespSocketDoubleState::$readChunks[0] = mb_substr($chunk, $length, null, '8bit');

        return mb_substr($chunk, 0, $length, '8bit');
    }

    array_shift(RespSocketDoubleState::$readChunks);

    return $chunk;
}

/**
 * The read-deadline refinement: recorded for the tests to observe,
 * answered by the engine's own call.
 *
 * @param resource $stream the stream the engine's own call accepts
 */
function stream_set_timeout($stream, int $seconds, int $microseconds = 0): bool
{
    RespSocketDoubleState::$setTimeoutCalls[] = [$seconds, $microseconds];

    return engine_stream_set_timeout($stream, $seconds, $microseconds);
}
