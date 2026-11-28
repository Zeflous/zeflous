<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;
use Random\RandomException;

/**
 * The outbox row's own identity: thirty-two lowercase hexadecimal
 * characters, minted from the engine's random source the moment a
 * transactional defer spells a new row -- the outbox twin of
 * {@see DeliveryEntryId}, carrying the same grammar and the same
 * `D`-anchored refusal (#786: PCRE's bare `$` also matches
 * immediately before a final newline, so the canonical spelling
 * must never silently carry a control byte). Where the delivery
 * entry names ONE listener's pending delivery, the outbox row
 * names the EVENT the host appended -- the relay's defer mints
 * the delivery identities the row becomes, each fresh, exactly as
 * the durable arm's own doctrine spells; the row's identity is
 * only the mark the relay's removal answers, and the append order
 * the row's own appended-at moment answers.
 *
 * A retried relay never mints a new row identity on purpose: the
 * row the relay re-reads after its own rollback is the SAME row
 * the same spelling names, which is what keeps the relay's
 * read-handoff-removal window free of the doubling a second
 * identity would answer for.
 */
final readonly class OutboxMessageId
{
    private const int HEX_LENGTH = 32;

    private string $id;

    /**
     * @throws InvalidArgumentException when the spelling is not
     *                                  thirty-two lowercase hexadecimal characters
     */
    public function __construct(string $id)
    {
        if (preg_match('/^[0-9a-f]{' . self::HEX_LENGTH . '}$/D', $id) !== 1) {
            throw new InvalidArgumentException(
                'An outbox message id must be exactly ' . self::HEX_LENGTH
                . " lowercase hexadecimal characters, got '" . $id . "'.",
            );
        }

        $this->id = $id;
    }

    /**
     * Mints a fresh identity from the engine's random source -- a
     * 128-bit spelling whose collision odds are the same family of
     * small the delivery entry's own identifier carries.
     *
     * @throws RandomException when the engine's random source
     *                         refuses to answer
     */
    public static function mint(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    /**
     * The canonical spelling -- the string the row's own column
     * carries.
     */
    public function spelling(): string
    {
        return $this->id;
    }
}
