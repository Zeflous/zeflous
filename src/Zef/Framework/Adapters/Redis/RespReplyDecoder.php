<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Redis;

use InvalidArgumentException;

/**
 * The RESP reply grammar's reading half: the five reply types the
 * wire answers -- the simple string, the error, the integer, the
 * bulk string (its null spelling included), and the array (its null
 * spelling and its nested elements included) -- pulled one reply at
 * a time from the wire's own buffered discipline. The decode is
 * fail-closed in every direction: an unknown leading byte, a
 * non-numeric count, or a negative count that is not the null
 * spelling is refused with the leading byte and the line named,
 * never guessed past -- and the server's own error reply is carried
 * out as the refusal it is, its message intact. The bulk reply's
 * own decode is the {@see RespBulkReply} fold the type byte hands
 * the length line to.
 *
 * @phpstan-type Reply null|int|string|list<int|string|null|list<mixed>>
 */
final readonly class RespReplyDecoder
{
    public function __construct(
        private RespReader $respReader,
        private RespBulkReply $respBulkReply,
    ) {
    }

    /**
     * Decodes the next reply the wire answers.
     *
     * @return Reply the reply's own shape -- a simple string as the
     *               string, an integer as the integer, the null spellings as
     *               null, an array as the list of its decoded elements (one
     *               nesting level spelled, the deeper mix the narrowing
     *               fold's own question -- the payload map's own
     *               one-level-then-mixed precedent)
     *
     * @throws InvalidArgumentException when the reply is an error the
     *                                  server answered, or the bytes are not a
     *                                  reply the grammar admits
     */
    public function nextReply(): array|int|string|null
    {
        $line = $this->respReader->readLine();

        if ($line === '') {
            throw new InvalidArgumentException(
                "The Redis reply's leading line is empty -- not a reply the grammar admits.",
            );
        }

        $type = $line[0];
        $payload = mb_substr($line, 1, null, '8bit');

        return match ($type) {
            '+' => $payload,
            '-' => throw new InvalidArgumentException(
                'The Redis server refused the command: ' . $payload,
            ),
            ':' => $this->integerOf($payload, ':'),
            '$' => $this->respBulkReply->decode($payload),
            '*' => $this->arrayOf($payload),
            default => throw new InvalidArgumentException(
                "The Redis reply's leading byte '{$type}' is not a RESP type.",
            ),
        };
    }

    /**
     * The integer reply's own grammar: a decimal run, an optional
     * leading minus.
     */
    private function integerOf(string $payload, string $type): int
    {
        if (preg_match('/^-?\d+$/', $payload) !== 1) {
            throw new InvalidArgumentException(
                "The Redis reply of type '{$type}' is not a decimal integer: '{$payload}'.",
            );
        }

        return (int) $payload;
    }

    /**
     * The array reply: the null spelling, or the decoded elements in
     * their own order.
     *
     * @return null|list<Reply>
     */
    private function arrayOf(string $payload): ?array
    {
        if ($payload === '-1') {
            return null;
        }

        $count = $this->integerOf($payload, '*');

        if ($count < 0) {
            throw new InvalidArgumentException(
                "The Redis array reply's count '{$payload}' is not a count the grammar admits.",
            );
        }

        $elements = [];

        for ($index = 0; $index < $count; ++$index) {
            $elements[] = $this->nextReply();
        }

        return $elements;
    }
}
