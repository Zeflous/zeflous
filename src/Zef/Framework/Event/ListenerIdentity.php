<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The listener's own durable identity: the spelling a durable defer
 * records a pool listener by, and the spelling a later drain
 * resolves back into a listener object through the
 * {@see EventListenerResolver} port. The object identity the
 * in-process books attribute by dies with the process that minted
 * it; this spelling outlives the process, because the durable
 * arm's whole promise is that a delivery can be admitted now and
 * answered in a process that has not started yet.
 *
 * The grammar is the event name's own family on purpose: a
 * letter-first spelling over letters, digits, dots, dashes and
 * underscores, within the same 64-character ceiling, so the two
 * vocabularies stay printable, comparable and free of the
 * whitespace and control bytes that would make a file name, a
 * JSON key or a log line ambiguous later. A listener that is not
 * the application's own service -- a closure, a throwaway object
 * -- has no identity worth spelling, and the durable admission
 * refuses it by class name rather than guessing one.
 */
final readonly class ListenerIdentity
{
    private const int MAXIMUM_LENGTH = 64;

    private string $identity;

    /**
     * @throws InvalidArgumentException when the spelling is not a
     *                                  printable listener identity within the grammar's bounds
     */
    public function __construct(string $identity)
    {
        if ($identity === '' || mb_strlen($identity) > self::MAXIMUM_LENGTH) {
            throw new InvalidArgumentException(
                'A listener identity must be a non-empty string of at most '
                . self::MAXIMUM_LENGTH . ' characters, got a length of '
                . mb_strlen($identity) . '.',
            );
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9._-]*$/', $identity) !== 1) {
            throw new InvalidArgumentException(
                'A listener identity must start with a letter and continue'
                . ' with letters, digits, dots, dashes or underscores, got'
                . " '{$identity}'.",
            );
        }

        $this->identity = $identity;
    }

    /**
     * The canonical spelling every participant addresses the
     * listener by durably -- the one string a durable record, a
     * resolver table and a dead letter all carry, so equality stays
     * byte-identical across processes.
     */
    public function spelling(): string
    {
        return $this->identity;
    }
}
