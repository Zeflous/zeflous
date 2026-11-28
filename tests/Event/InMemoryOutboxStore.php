<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use Override;
use Zef\Framework\Event\OutboxMessage;
use Zef\Framework\Event\OutboxStore;

/**
 * The in-memory stand-in for the outbox store: the port's verbs
 * answered by arrays, with an ordered action ledger a test reads
 * to pin the PROTOCOL (the read-order walk, the handoff-and-
 * removal unit, the both-or-neither a refused handoff rolls back)
 * independently of any adapter's engine mechanics. The
 * exactly-once handoff doctrine is spelled honestly here: the
 * relay reads, hands off, and removes as ONE unit -- a handoff
 * that refuses rolls the whole pass back and every row stays
 * pending, exactly as the port's contract demands.
 *
 * @internal
 */
final class InMemoryOutboxStore implements OutboxStore
{
    /** @var array<string, OutboxMessage> the pending rows, keyed by message id */
    private array $pending = [];

    /** @var list<string> the ordered action ledger, the protocol's own trace */
    private array $actions = [];

    /**
     * @param int $capacity the pending bound (>= 1) the append's
     *                      arithmetic reads
     */
    public function __construct(private readonly int $capacity = 64)
    {
    }

    #[Override]
    public function append(OutboxMessage ...$outboxMessages): void
    {
        if ($outboxMessages === []) {
            return;
        }

        $demanded = \count($outboxMessages);

        if (\count($this->pending) + $demanded > $this->capacity) {
            throw new InvalidArgumentException(
                'The outbox store is full: capacity ' . $this->capacity
                . ', occupied ' . \count($this->pending) . ', demanded '
                . $demanded . ' -- relay before deferring more.',
            );
        }

        foreach ($outboxMessages as $outboxMessage) {
            $this->pending[$outboxMessage->messageId()->spelling()] = $outboxMessage;
            $this->actions[] = 'append:' . $outboxMessage->messageId()->spelling();
        }
    }

    #[Override]
    public function relay(int $limit, callable $handoff): int
    {
        if ($limit < 1) {
            throw new InvalidArgumentException(
                'A relay pass relays at least one row, got ' . $limit . '.',
            );
        }

        $relayed = $this->readPending($limit);

        if ($relayed === []) {
            $this->actions[] = 'relay:empty';

            return 0;
        }

        $handoff(...$relayed);

        foreach ($relayed as $outboxMessage) {
            unset($this->pending[$outboxMessage->messageId()->spelling()]);
            $this->actions[] = 'relayed:' . $outboxMessage->messageId()->spelling();
        }

        return \count($relayed);
    }

    #[Override]
    public function pendingCount(): int
    {
        return \count($this->pending);
    }

    /**
     * The ordered action ledger, the protocol's own trace.
     *
     * @return list<string>
     */
    public function actions(): array
    {
        return $this->actions;
    }

    /**
     * The pending rows, keyed by message id, for count and identity
     * assertions.
     *
     * @return array<string, OutboxMessage>
     */
    public function pendingRows(): array
    {
        return $this->pending;
    }

    /**
     * Reads the pending rows in the read order -- appended-at then
     * message identity, the DU9 ordering doctrine the adapter's
     * index carries for free, spelled by the double's own walk.
     *
     * @param int $limit the most rows one pass reads
     *
     * @return list<OutboxMessage>
     */
    private function readPending(int $limit): array
    {
        $rows = [...$this->pending];

        usort($rows, static fn (
            OutboxMessage $left,
            OutboxMessage $right,
        ): int => [$left->appendedAt(), $left->messageId()->spelling()]
            <=> [$right->appendedAt(), $right->messageId()->spelling()]);

        return \array_slice($rows, 0, $limit);
    }
}
