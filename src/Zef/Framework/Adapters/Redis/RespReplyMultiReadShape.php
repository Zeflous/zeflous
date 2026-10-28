<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Redis;

use InvalidArgumentException;

/**
 * Whether a decoded reply is the array shape a multi-read command
 * answers: one element per key asked, each a bulk string or the null
 * bulk -- the null IS the absent record, never a refusal -- and a
 * reply that decodes into the wrong shape is refused with both
 * shapes named rather than coerced past. The range command's own
 * array spelling (every element a string) is the {@see
 * RespReplyListShape} fold, the scalar replies' own narrowing the
 * {@see RespReplyShape} fold, and the refusal's own construction the
 * {@see RespReplyRefusal} fold every guard throws.
 */
final readonly class RespReplyMultiReadShape
{
    /**
     * Narrows the reply to the array of bulk strings and null bulks a
     * multi-read command answers.
     *
     * @param mixed  $reply   the decoded reply
     * @param string $command the command whose answer is being narrowed
     *
     * @return list<?string>
     *
     * @throws InvalidArgumentException when the reply is not the array
     *                                  the command answers
     */
    public function bulkOrNullList(mixed $reply, string $command): array
    {
        if (!\is_array($reply)) {
            throw RespReplyRefusal::refusal(
                $reply,
                $command,
                'an array of bulk strings and null bulks',
            );
        }

        $elements = [];

        foreach ($reply as $element) {
            if (!\is_string($element) && $element !== null) {
                throw RespReplyRefusal::refusal(
                    $reply,
                    $command,
                    'an array of bulk strings and null bulks',
                );
            }

            $elements[] = $element;
        }

        return $elements;
    }
}
