<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The event's own name, validated once at the vocabulary's front
 * door: a non-empty dot-, dash- and underscore-separated spelling
 * a definition, a subscription and a dispatch all address an event
 * by -- so a typo in any of the three is refused here, named, and
 * never reaches a listener.
 *
 * The grammar is deliberately narrow: it starts with a letter,
 * continues with letters, digits, dots, dashes and underscores,
 * and stays within 64 characters. That is enough room for the
 * domain conventions the roadmap spells (`user.created`,
 * `order.paid`) while keeping every name printable, comparable and
 * free of whitespace, control bytes and signature spellings that
 * would make a wildcard match ambiguous later.
 */
final readonly class EventName
{
    private const int MAXIMUM_LENGTH = 64;

    private string $name;

    /**
     * @throws InvalidArgumentException when the spelling is not a
     *                                  printable event name within the grammar's bounds
     */
    public function __construct(string $name)
    {
        if ($name === '' || mb_strlen($name) > self::MAXIMUM_LENGTH) {
            throw new InvalidArgumentException(
                'An event name must be a non-empty string of at most '
                . self::MAXIMUM_LENGTH . ' characters, got a length of '
                . mb_strlen($name) . '.',
            );
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9._-]*$/', $name) !== 1) {
            throw new InvalidArgumentException(
                'An event name must start with a letter and continue with'
                . " letters, digits, dots, dashes or underscores, got '{$name}'.",
            );
        }

        $this->name = $name;
    }

    /**
     * The canonical spelling every participant addresses the event
     * by -- the one string a definition, a subscription and a
     * dispatch record all carry, so equality stays byte-identical.
     */
    public function spelling(): string
    {
        return $this->name;
    }
}
