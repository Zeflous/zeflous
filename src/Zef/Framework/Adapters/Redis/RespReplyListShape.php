<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Redis;

use InvalidArgumentException;

/**
 * Whether a decoded reply is the array shape a range command
 * answers: every element a bulk string, the empty array the empty
 * range -- a reply that decodes into the wrong shape is refused
 * with both shapes named rather than coerced past. The multi-read
 * command's own array spelling (the null bulk an element) is the
 * {@see RespReplyMultiReadShape} fold, the scalar replies' own
 * narrowing the {@see RespReplyShape} fold, and the refusal's own
 * construction the {@see RespReplyRefusal} fold every guard throws.
 */
final readonly class RespReplyListShape
{
    /**
     * Narrows the reply to the array of bulk strings a range command
     * answers.
     *
     * @param mixed  $reply   the decoded reply
     * @param string $command the command whose answer is being narrowed
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when the reply is not the array
     *                                  of strings the command answers
     */
    public function bulkList(mixed $reply, string $command): array
    {
        if (!\is_array($reply)) {
            throw RespReplyRefusal::refusal(
                $reply,
                $command,
                'an array of bulk strings',
            );
        }

        $members = [];

        foreach ($reply as $member) {
            if (!\is_string($member)) {
                throw RespReplyRefusal::refusal(
                    $reply,
                    $command,
                    'an array of bulk strings',
                );
            }

            $members[] = $member;
        }

        return $members;
    }
}
