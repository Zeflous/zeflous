<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\Event;

/**
 * The file delivery boundary's double state: the armed answers the
 * function doubles read, each null (or empty) by default so
 * passthrough is the behaviour every unarmed test sees, and the
 * reset a test's tear-down calls so no armed answer leaks into a
 * later suite.
 *
 * @internal
 */
final class DeliveryFileDoubleState
{
    /** @var null|false|int the armed file_put_contents answer (a short int simulates the partial write) */
    public static false|int|null $filePutContentsAnswer = null;

    /** @var null|bool the armed chmod answer */
    public static ?bool $chmodAnswer = null;

    /** @var list<null|bool> the armed rename answers, consumed in call order (null = the engine's own answer, so a successfully finalised entry is real on disk) */
    public static array $renameAnswers = [];

    /** @var null|bool the armed unlink answer */
    public static ?bool $unlinkAnswer = null;

    /** @var null|bool the armed mkdir answer */
    public static ?bool $mkdirAnswer = null;

    /** @var null|false|list<string> the armed scandir answer */
    public static array|false|null $scandirAnswer = null;

    /** @var null|false|string the armed file_get_contents answer */
    public static false|string|null $fileGetContentsAnswer = null;

    /** @var null|false|string the armed realpath answer (false arms the unresolvable-directory refusal) */
    public static false|string|null $realpathAnswer = null;

    /**
     * Clears every armed answer -- the between-tests reset.
     */
    public static function reset(): void
    {
        self::$filePutContentsAnswer = null;
        self::$chmodAnswer = null;
        self::$renameAnswers = [];
        self::$unlinkAnswer = null;
        self::$mkdirAnswer = null;
        self::$scandirAnswer = null;
        self::$fileGetContentsAnswer = null;
        self::$realpathAnswer = null;
    }
}
