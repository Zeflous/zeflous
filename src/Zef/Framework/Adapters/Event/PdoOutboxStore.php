<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Override;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Event\OutboxMessage;
use Zef\Framework\Event\OutboxStore;

/**
 * The outbox store's database answer: the transactional arm's
 * persistence port answered over the database family's own
 * connection engine -- the port-then-adapter order the durable
 * arm's store spelled first, the same engine composed for the
 * write side the transactional lane owns. The connection every
 * fold rides passes through the port boundary fold first
 * ({@see PdoOutboxBoundary}), so the engine's own typed refusals
 * arrive as the port's declared family on every verb alike; the
 * row rides whatever transaction the caller's scope carries over
 * that connection, because the append NEVER opens a scope of its
 * own -- the business write and the outbox row land together or
 * neither lands, the exactly-once emission the at-least-once
 * durable arm deliberately defers to this lane for (KDD-DU1).
 *
 * The relay is the port's other half: one pass -- the read, the
 * handoff's writes and the relayed rows' removal -- one unit the
 * engine's own managed scope answers, the exactly-once handoff
 * into the delivery store the same connection spells. The
 * composition demand is the port's own: the outbox and the
 * delivery store the relay feeds ride the SAME connection the
 * host spells, so the read, the defers and the removals answer
 * one atomic unit.
 *
 * One connection, one lifetime (the Redis answer's own doctrine,
 * carried): a dead wire is a refusal every later verb answers, and
 * the deployment's own glue decides what a dead connection means.
 */
final readonly class PdoOutboxStore implements OutboxStore
{
    private PdoOutboxAdmission $pdoOutboxAdmission;

    private PdoOutboxRelay $pdoOutboxRelay;

    private PdoOutboxEntries $pdoOutboxEntries;

    /**
     * @param ConnectionInterface $connection the database family's own port -- one
     *                                        connection, one lifetime, shared with the delivery
     *                                        store the relay feeds (the composition demand
     *                                        the port's doctrine spells)
     * @param int                 $capacity   the pending bound (>= 1): the outbox
     *                                        keeps at most this many un-relayed rows
     * @param string              $prefix     the store's table namespace: the
     *                                        deployment's own spelling the table
     *                                        name starts with
     *
     * @throws InvalidArgumentException when the capacity is below
     *                                  one, the prefix refuses the identifier
     *                                  grammar, the connection's driver word owns
     *                                  no dialect, or the store's table cannot be
     *                                  ensured -- the honest halt, the store's
     *                                  table named by the statement the engine
     *                                  refused (the boot-time-connection doctrine
     *                                  the delivery store's composition arm
     *                                  spelled)
     */
    public function __construct(
        ConnectionInterface $connection,
        int $capacity,
        string $prefix,
    ) {
        $pdoOutboxBoundary = new PdoOutboxBoundary($connection);
        $pdoOutboxTables = new PdoOutboxTables($prefix);
        $pdoOutboxDialect = new PdoOutboxDialect($pdoOutboxBoundary->driver());
        $this->pdoOutboxEntries = new PdoOutboxEntries($pdoOutboxBoundary, $pdoOutboxTables);
        $this->pdoOutboxAdmission = new PdoOutboxAdmission(
            $pdoOutboxBoundary,
            $pdoOutboxTables,
            $this->pdoOutboxEntries,
            $capacity,
        );
        $this->pdoOutboxRelay = new PdoOutboxRelay(
            $pdoOutboxBoundary,
            new PdoOutboxPending($pdoOutboxBoundary, $pdoOutboxTables),
            new PdoOutboxRecords(new PdoOutboxPayload()),
            $this->pdoOutboxEntries,
        );
        new PdoOutboxSchema(
            $pdoOutboxBoundary,
            $pdoOutboxDialect,
            $pdoOutboxTables,
        )->ensure();
    }

    #[Override]
    public function append(OutboxMessage ...$outboxMessages): void
    {
        $this->pdoOutboxAdmission->admit(...$outboxMessages);
    }

    #[Override]
    public function relay(int $limit, callable $handoff): int
    {
        return $this->pdoOutboxRelay->relay($limit, $handoff);
    }

    #[Override]
    public function pendingCount(): int
    {
        return $this->pdoOutboxAdmission->pendingCount();
    }
}
