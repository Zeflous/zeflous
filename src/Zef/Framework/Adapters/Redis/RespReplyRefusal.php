<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Redis;

use InvalidArgumentException;

/**
 * The reply refusal's own construction: the message that names the
 * command, the shape its grammar answers, and the shape the reply
 * actually decoded into -- both shapes named because a reply that
 * coerces past its shape is a reply the command never sent. The
 * spelling of the decoded shape is this fold's own vocabulary, the
 * narrowing guards' shared refusal arm.
 */
final readonly class RespReplyRefusal
{
    /**
     * The refusal both shapes are named by.
     *
     * @param mixed  $reply    the decoded reply
     * @param string $command  the command whose answer was being narrowed
     * @param string $demanded the shape the command's grammar answers
     */
    public static function refusal(mixed $reply, string $command, string $demanded): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "The Redis reply for '{$command}' is refused: the command answers"
            . ' ' . $demanded . ', the reply decoded into '
            . self::spellingOf($reply) . '.',
        );
    }

    /**
     * The decoded reply's own shape, named for the refusal.
     */
    private static function spellingOf(mixed $reply): string
    {
        return match (true) {
            \is_int($reply) => 'the integer ' . $reply,
            \is_string($reply) => "the string '" . $reply . "'",
            $reply === null => 'the null bulk',
            \is_array($reply) => 'an array of ' . \count($reply) . ' elements',
            default => get_debug_type($reply),
        };
    }
}
