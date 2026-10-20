<?php

declare(strict_types=1);

namespace Zef\Test\Support\Logging;

/**
 * The JSON log line's function doubles' state, typed: the call records
 * and the configurable failure arms the doubles
 * ({@see json_line_function_doubles.php}) answer from and the tests seed
 * and read. The doubles default to full passthrough (they delegate to the
 * global functions and answer the engine's own result), so every other
 * suite that exercises the same functions keeps its behaviour until a
 * test explicitly arms a failure or a byte budget here.
 *
 * @internal
 */
final class JsonLineDoubleState
{
    /** Whether the doubles record their calls; the suite-wide bootstrap loads them disarmed. */
    public static bool $record = false;

    /** @var list<array{int, bool}> the flock calls: [operation, result] */
    public static array $flockCalls = [];

    /** @var list<array{string, false|int}> the fwrite calls: [payload, answered count] */
    public static array $fwriteCalls = [];

    /** Forcing flock's answer; null delegates to the global call. */
    public static ?bool $flockAnswer = null;

    /** @var list<false|int> the queued fwrite answers, consumed before any real write */
    public static array $fwriteAnswers = [];

    /**
     * The per-call byte budget the fwrite double writes (the short-write
     * policy); null delegates each call to the global fwrite.
     */
    public static ?int $fwriteChunk = null;
}
