<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\Temp;

/**
 * The shared state the temp lane's function doubles read: the armed
 * answers the engine-boundary calls return instead of the engine's
 * own, so a refusal arm that needs an answer the real filesystem
 * cannot produce in a test process (an unfinalisable rename, an
 * unscannable root, an unresolvable address) is observable without
 * depending on the runner's own environment.
 *
 * Every armed answer is reset in the tear-down of each test that
 * arms one -- the doubles file's own discipline (the session
 * family's {@see \Zef\Test\Support\Adapters\Session\SessionFileDoubleState}
 * shape, replayed for the temp lane's own engine-boundary calls).
 *
 * @internal
 */
final class TempFileDoubleState
{
    /** @var null|bool the armed chmod answer (false arms the unsecurable-scratch refusal) */
    public static ?bool $chmodAnswer = null;

    /** @var null|bool the armed rename answer (false arms the unfinalisable rename, without the engine call) */
    public static ?bool $renameAnswer = null;

    /** @var null|false the armed scandir answer (false arms the unscannable-root refusal) */
    public static ?false $scandirAnswer = null;

    /** @var null|false the armed realpath answer (false arms the unresolvable-address refusal) */
    public static ?false $realpathAnswer = null;

    /** @var null|false the armed unlink answer (false arms the untakeable-removal refusal, without the engine call) */
    public static ?false $unlinkAnswer = null;

    /** @var null|false the armed file_exists answer (false arms the concurrent-retirement window the re-check owns) */
    public static ?false $fileExistsAnswer = null;

    /**
     * Resets every armed answer to the engine's own.
     */
    public static function reset(): void
    {
        self::$chmodAnswer = null;
        self::$renameAnswer = null;
        self::$scandirAnswer = null;
        self::$realpathAnswer = null;
        self::$unlinkAnswer = null;
        self::$fileExistsAnswer = null;
    }
}
