<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * One AMQP method identity: the class id and method id the wire
 * spells in every method frame's first four bytes, with the
 * human-readable spelling the refusal messages name a method by.
 * The vocabulary is closed -- one catalogue, keyed by the
 * protocol's own spelling, answering both directions: the
 * spelling the family pins ({@see of()}, refused when the family
 * never pinned it) and the numeric identity a frame carried
 * ({@see ofFrame()}, answering the pinned spelling when the
 * catalogue knows the pair and the numeric spelling when it does
 * not, so an unexpected method is always refusable with both
 * methods named).
 */
final readonly class AmqpMethod
{
    /**
     * The methods the family pins -- the protocol's own spellings,
     * their class and method ids the catalogue's own values.
     *
     * @var array<string, array{int, int}>
     */
    private const array CATALOGUE = [
        'connection.start' => [10, 10],
        'connection.start-ok' => [10, 11],
        'connection.tune' => [10, 30],
        'connection.tune-ok' => [10, 31],
        'connection.open' => [10, 40],
        'connection.open-ok' => [10, 41],
        'connection.close' => [10, 50],
        'channel.open' => [20, 10],
        'channel.open-ok' => [20, 11],
        'channel.close' => [20, 40],
        'queue.declare' => [50, 10],
        'queue.declare-ok' => [50, 11],
        'basic.publish' => [60, 40],
        'basic.get' => [60, 70],
        'basic.get-ok' => [60, 71],
        'basic.get-empty' => [60, 72],
        'basic.ack' => [60, 80],
        'tx.select' => [90, 10],
        'tx.select-ok' => [90, 11],
        'tx.commit' => [90, 20],
        'tx.commit-ok' => [90, 21],
    ];

    private function __construct(
        private int $classId,
        private int $methodId,
        private string $spelling,
    ) {
    }

    /**
     * The method a pinned spelling answers -- the family's own
     * vocabulary, refused when the spelling is not one it pinned.
     *
     * @throws InvalidArgumentException when the spelling is not one
     *                                  the family pins
     */
    public static function of(string $spelling): self
    {
        $identity = self::CATALOGUE[$spelling] ?? null;

        if ($identity === null) {
            throw new InvalidArgumentException(
                "The AMQP method spelling '{$spelling}' is not one the"
                . ' family pins.',
            );
        }

        return new self($identity[0], $identity[1], $spelling);
    }

    /**
     * The method a frame's own numeric identity answers -- the
     * pinned spelling when the catalogue knows the pair, the
     * numeric spelling when it does not.
     */
    public static function ofFrame(int $classId, int $methodId): self
    {
        foreach (self::CATALOGUE as $spelling => $identity) {
            if ($identity[0] === $classId && $identity[1] === $methodId) {
                return new self($classId, $methodId, $spelling);
            }
        }

        return new self($classId, $methodId, 'class ' . $classId . ' method ' . $methodId);
    }

    /**
     * The method's class id -- the connection (10), channel (20),
     * queue (50), basic (60) or transaction (90) family.
     */
    public function classId(): int
    {
        return $this->classId;
    }

    /**
     * The method's own id inside its class.
     */
    public function methodId(): int
    {
        return $this->methodId;
    }

    /**
     * The human-readable spelling the refusal messages name the
     * method by.
     */
    public function spelling(): string
    {
        return $this->spelling;
    }

    /**
     * Whether two identities are the same method.
     */
    public function equals(self $amqpMethod): bool
    {
        return $this->classId === $amqpMethod->classId
            && $this->methodId === $amqpMethod->methodId;
    }
}
