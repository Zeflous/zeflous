<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\View;

/**
 * The view adapter's double state: the armed answers the function
 * doubles read, each null by default so passthrough is the behaviour
 * every unarmed test sees, and the reset a test's tear-down calls so
 * no armed answer leaks into a later suite.
 *
 * @internal
 */
final class ViewFileDoubleState
{
    /** @var null|false|string the armed realpath answer (false arms the unresolvable-path refusals both folds spell) */
    public static false|string|null $realpathAnswer = null;

    /** @var null|false|int the armed fileperms answer (false arms the unreadable-permissions refusal) */
    public static false|int|null $filepermsAnswer = null;

    /**
     * Clears every armed answer -- the between-tests reset.
     */
    public static function reset(): void
    {
        self::$realpathAnswer = null;
        self::$filepermsAnswer = null;
    }
}
