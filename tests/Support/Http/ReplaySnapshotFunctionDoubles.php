<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * The replay snapshot boundary's function doubles, owned by one support
 * class for the whole suite: the doubles themselves live in their own real
 * file ({@see replay_snapshot_function_doubles.php}), declared in the
 * boundary's own namespace so PHP resolves the boundary's unqualified
 * calls to them first.
 *
 * Every test that exercises the writer's failure containment or the
 * decoder's encode contract shares this one definition, so the answers are
 * order-independent by construction rather than by discovery luck. The
 * file is required once per process (require_once semantics), and this
 * class's define() resets the typed state
 * ({@see ReplaySnapshotDoubleState}) the doubles answer from, so each test
 * starts from the full-passthrough baseline.
 *
 * @internal
 */
final class ReplaySnapshotFunctionDoubles
{
    /**
     * Resets the typed state the doubles answer from (every failure arm
     * disarmed, every call record empty, recording on) and makes sure their
     * one definition is loaded in this process -- the bootstrap already
     * loaded it suite-wide; this re-arm is per-test.
     */
    public static function define(): void
    {
        self::reset();
        ReplaySnapshotDoubleState::$record = true;

        require_once __DIR__ . '/replay_snapshot_function_doubles.php';
    }

    /**
     * Clears every armed answer and call record and stops recording, so
     * the doubles answer plain passthrough for the rest of the suite once
     * the tests that needed them are done.
     */
    public static function reset(): void
    {
        ReplaySnapshotDoubleState::$chmodCalls = [];
        ReplaySnapshotDoubleState::$flockCalls = [];
        ReplaySnapshotDoubleState::$fwriteCalls = [];
        ReplaySnapshotDoubleState::$jsonEncodeCalls = [];
        ReplaySnapshotDoubleState::$unlinkCalls = [];
        ReplaySnapshotDoubleState::$errorLogMessages = [];
        ReplaySnapshotDoubleState::$chmodAnswer = null;
        ReplaySnapshotDoubleState::$flockAnswer = null;
        ReplaySnapshotDoubleState::$fwriteAnswers = [];
        ReplaySnapshotDoubleState::$unlinkAnswers = [];
        ReplaySnapshotDoubleState::$fwriteChunk = null;
        ReplaySnapshotDoubleState::$record = false;
    }
}
