<?php

declare(strict_types=1);

namespace Zef\Test\Support\Logging;

/**
 * The JSON log line boundary's function doubles, owned by one support
 * class for the whole suite: the doubles themselves live in their own
 * real file ({@see json_line_function_doubles.php}), declared in the
 * boundary's own namespace so PHP resolves the boundary's unqualified
 * calls to them first.
 *
 * Every test that exercises the writer's lock hygiene or the record
 * writer's short-write containment shares this one definition, so the
 * answers are order-independent by construction rather than by
 * discovery luck. The file is required once per process (require_once
 * semantics), and this class's define() resets the typed state
 * ({@see JsonLineDoubleState}) the doubles answer from, so each test
 * starts from the full-passthrough baseline.
 *
 * @internal
 */
final class JsonLineFunctionDoubles
{
    /**
     * Resets the typed state the doubles answer from (every failure arm
     * disarmed, every call record empty, recording on) and makes sure
     * their one definition is loaded in this process -- the bootstrap
     * already loaded it suite-wide; this re-arm is per-test.
     */
    public static function define(): void
    {
        self::reset();
        JsonLineDoubleState::$record = true;

        require_once __DIR__ . '/json_line_function_doubles.php';
    }

    /**
     * Clears every armed answer and call record and stops recording, so
     * the doubles answer plain passthrough for the rest of the suite once
     * the tests that needed them are done.
     */
    public static function reset(): void
    {
        JsonLineDoubleState::$flockCalls = [];
        JsonLineDoubleState::$fwriteCalls = [];
        JsonLineDoubleState::$flockAnswer = null;
        JsonLineDoubleState::$fwriteAnswers = [];
        JsonLineDoubleState::$fwriteChunk = null;
        JsonLineDoubleState::$record = false;
    }
}
