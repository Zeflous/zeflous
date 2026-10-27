<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;
use Random\RandomException;

/**
 * The durable entry's own identity: thirty-two lowercase
 * hexadecimal characters, minted from the engine's random source
 * the moment a durable admission or a retry spells a new entry.
 * The in-process arm never needs an entry identity -- the object
 * IS the entry -- but a durable spelling must name the thing it
 * carries, because a file name, a dead record and a settle call
 * all have to agree on which entry they mean without the object
 * to point at.
 *
 * A retry mints a FRESH identity on purpose: the retried entry is
 * a new entry standing at the next attempt number (the
 * in-process arm's own doctrine), and a fresh identity is what
 * keeps the write-the-answer-then-settle-the-question ordering of
 * a durable drain free of the window where settling the spent
 * entry would also settle the retry that replaced it.
 */
final readonly class DeliveryEntryId
{
    private const int HEX_LENGTH = 32;

    private string $id;

    /**
     * @throws InvalidArgumentException when the spelling is not
     *                                  thirty-two lowercase hexadecimal characters
     */
    public function __construct(string $id)
    {
        if (preg_match('/^[0-9a-f]{' . self::HEX_LENGTH . '}$/', $id) !== 1) {
            throw new InvalidArgumentException(
                'A delivery entry id must be exactly ' . self::HEX_LENGTH
                . " lowercase hexadecimal characters, got '" . $id . "'.",
            );
        }

        $this->id = $id;
    }

    /**
     * Mints a fresh identity from the engine's random source -- a
     * 128-bit spelling whose collision odds are the same family of
     * small the replay snapshot's own identifier carries.
     *
     * @throws RandomException when the engine's random source
     *                         refuses to answer
     */
    public static function mint(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    /**
     * The canonical spelling -- the one string an entry file is
     * named by, a dead record is keyed by and a settle call means.
     */
    public function spelling(): string
    {
        return $this->id;
    }
}
