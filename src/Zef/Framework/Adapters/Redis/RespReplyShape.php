<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Redis;

use InvalidArgumentException;

/**
 * Whether a decoded reply is the scalar shape its command answers:
 * the status commands the simple string, the counters the integer,
 * the reads the bulk or the null bulk -- a reply that decodes into
 * the wrong shape is refused with both shapes named rather than
 * coerced past. The fail-closed posture the durable spelling's own
 * record gate carries ({@see
 * \Zef\Framework\Event\DeliveryRecordShape}), replayed at the wire's
 * answer side; the array replies' own narrowing is the {@see
 * RespReplyListShape} fold, and the refusal's own construction is
 * the {@see RespReplyRefusal} fold every guard throws.
 */
final readonly class RespReplyShape
{
    /**
     * Narrows the reply to the simple string a status command
     * answers -- the `OK` every successful write carries. The verb is
     * the guard alone: no caller reads the status back, so the method
     * answers nothing -- it passes, or it refuses with both shapes
     * named.
     *
     * @param mixed  $reply   the decoded reply
     * @param string $command the command whose answer is being narrowed
     *
     * @throws InvalidArgumentException when the reply is not the
     *                                  string the command answers
     */
    public function status(mixed $reply, string $command): void
    {
        if (!\is_string($reply)) {
            throw RespReplyRefusal::refusal($reply, $command, 'a status string');
        }
    }

    /**
     * Narrows the reply to the integer a counter command answers.
     *
     * @param mixed  $reply   the decoded reply
     * @param string $command the command whose answer is being narrowed
     *
     * @throws InvalidArgumentException when the reply is not the
     *                                  integer the command answers
     */
    public function integer(mixed $reply, string $command): int
    {
        if (!\is_int($reply)) {
            throw RespReplyRefusal::refusal($reply, $command, 'an integer');
        }

        return $reply;
    }

    /**
     * Narrows the reply to the bulk string or the null bulk a read
     * command answers -- the null IS the absent answer, never a
     * refusal.
     *
     * @param mixed  $reply   the decoded reply
     * @param string $command the command whose answer is being narrowed
     *
     * @throws InvalidArgumentException when the reply is not the bulk
     *                                  the command answers
     */
    public function bulk(mixed $reply, string $command): ?string
    {
        if (!\is_string($reply) && $reply !== null) {
            throw RespReplyRefusal::refusal(
                $reply,
                $command,
                'a bulk string or the null bulk',
            );
        }

        return $reply;
    }
}
