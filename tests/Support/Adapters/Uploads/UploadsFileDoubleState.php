<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\Uploads;

/**
 * The shared state the uploads lane's function doubles read: the
 * armed answers the engine-boundary calls return instead of the
 * engine's own, so a refusal arm that needs an answer the real
 * filesystem cannot produce in a test process (an unresolvable
 * path, an unreadable permission mask) is observable without
 * depending on the runner's own environment.
 *
 * Every armed answer is reset in the tear-down of each test that
 * arms one -- the doubles file's own discipline (the session
 * family's {@see \Zef\Test\Support\Adapters\Session\SessionFileDoubleState}
 * shape, replayed for the uploads lane's two engine-boundary calls).
 *
 * @internal
 */
final class UploadsFileDoubleState
{
    /** @var null|false the armed realpath answer (false arms the unresolvable-directory refusal) */
    public static ?false $realpathAnswer = null;

    /** @var null|false the armed fileperms answer (false arms the unreadable-permission-mask refusal) */
    public static ?false $filepermsAnswer = null;

    /**
     * Resets every armed answer to the engine's own.
     */
    public static function reset(): void
    {
        self::$realpathAnswer = null;
        self::$filepermsAnswer = null;
    }
}
