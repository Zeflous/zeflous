<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The book of dead letters: every delivery that exhausted its
 * attempts, in the order the drains wrote them. The book's one
 * judgment is attribution -- counting a listener's letters by
 * object identity -- which is exactly the number the poison-pill
 * quarantine reads (KDD-EA7).
 *
 * The book never forgets on its own: there is no pruning, no
 * ageing, no retry-from-the-book. A host that wants a letter gone
 * reads it and acts; the regime's honesty is the book keeping
 * what actually happened.
 */
final class DeadLetterBook
{
    /** @var list<DeadLetter> */
    private array $letters = [];

    /**
     * Writes one letter -- called by the drain fold the moment a
     * delivery spent its last attempt and still failed.
     */
    public function append(DeadLetter $deadLetter): void
    {
        $this->letters[] = $deadLetter;
    }

    /**
     * Every letter in the order the drains wrote them.
     *
     * @return list<DeadLetter>
     */
    public function all(): array
    {
        return $this->letters;
    }

    /**
     * How many letters the book holds.
     */
    public function count(): int
    {
        return \count($this->letters);
    }

    /**
     * How many letters are attributed to the given listener, by
     * object identity -- the quarantine threshold's own reading.
     */
    public function attributedTo(EventListener $eventListener): int
    {
        $attributed = 0;

        foreach ($this->letters as $letter) {
            if ($letter->eventListener() !== $eventListener) {
                continue;
            }

            ++$attributed;
        }

        return $attributed;
    }
}
