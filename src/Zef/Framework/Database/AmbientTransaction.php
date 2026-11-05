<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Throwable;

/**
 * The ambient transaction holder: the OPEN half of the transaction
 * story the explicit half ({@see TransactionManagerInterface}, the
 * manager's {@see TransactionManager::withTransaction()}) already
 * brackets. A frame deep in a call stack asks "run me inside a
 * transaction if none is open, else join the one that is" WITHOUT
 * threading the connection through every signature -- this holder is
 * the vocabulary that ask spells.
 *
 * The holder owns NO state of its own, by doctrine: {@see isOpen()}
 * delegates to the manager's own {@see TransactionManagerInterface::inTransaction()}
 * (the orchestration family's existing truth -- a second bookkeeping
 * the holder could drift from is refused), and {@see around()} is the
 * manager's managed scope with the connection-free closure -- the
 * two-case behaviour (the outermost bracket opening the real
 * transaction, a nested bracket joining as a savepoint) is the
 * savepoint machine's own nesting, never re-spelled here. Joining IS
 * nesting: an inner {@see around()} inside an outer one brackets at
 * depth+1, rolls back to its savepoint span on throw, and the
 * outermost commit drains the after-commit hooks ONCE -- the drain
 * discipline the orchestration slice pinned, inherited whole.
 *
 * Rollback-on-throw is the port's own doctrine, inherited not
 * renegotiated: a throwable anywhere inside the scope rolls back what
 * the scope owns, then propagates untouched. The closure receives
 * NOTHING -- the connection is the noise the ambient question exists
 * to remove; the deep frame's writes ride its own references
 * (gateway, repository, unit of work), and the flush owner keeps the
 * connection-bearing {@see TransactionManagerInterface::withTransaction()}
 * spelling. The holder never carries an isolation level: the
 * outermost declaration wins, and the ambient scope never widens what
 * the outermost declared.
 *
 * Instances are composed per ask (one request, one job, one command
 * run) over the manager the ask's connection already rides -- the
 * {@see DatabaseWiring::ambient()} fold answers the composition, the
 * `database.ambient` registration carrying the transient lifetime
 * the {@see UnitOfWork} registration spelled: never static, never
 * request-global. Two holders over one manager are observably the
 * same ambient scope, exactly because the holder delegates and never
 * re-bookkeeps -- the transient posture is safe by construction.
 */
final readonly class AmbientTransaction
{
    public function __construct(private TransactionManagerInterface $transactionManager)
    {
    }

    /**
     * True when a managed scope is already open here -- the manager's
     * own truth, delegated: raw connection transactions stay
     * invisible to it, exactly as the orchestration contract spells.
     */
    public function isOpen(): bool
    {
        return $this->transactionManager->inTransaction();
    }

    /**
     * Run $fn inside the managed scope: this call OPENS the scope when
     * none is open (the outermost bracket, the real transaction), and
     * JOINS when one is -- joining IS nesting, the inner bracket a
     * savepoint of the outermost transaction whose span rolls back on
     * throw while the whole scope commits once, at the outermost
     * boundary, with every after-commit hook draining there.
     *
     * The closure receives NOTHING: the connection is the noise the
     * ambient question exists to remove, so the body is spelled
     * connection-free -- the bracket's argument is discarded at the
     * adapter seam below (the one closure param the tree never spells
     * anywhere else, the discard the ambient vocabulary IS). The
     * writes ride the frame's own references; the flush owner keeps
     * withTransaction()'s connection-bearing spelling.
     *
     * @template T
     *
     * @param callable(): T $fn the scope's body, connection-free
     *
     * @return T whatever the body answers
     *
     * @throws Throwable the body threw, or the underlying commit
     *                   primitive failed: the scope's span rolls back and the
     *                   throwable propagates untouched (the port's own
     *                   doctrine, inherited)
     */
    public function around(callable $fn): mixed
    {
        // The connection arrives at the typed adapter seam and is
        // deliberately dropped: the seam stays typed to the manager's
        // CURRENT contract (a contract drift breaks loudly here), while
        // the ambient body receiving nothing IS the vocabulary this
        // holder exists to spell.
        return $this->transactionManager->withTransaction(
            /** @psalm-suppress UnusedClosureParam the discard IS the vocabulary */
            static fn (ConnectionInterface $connection): mixed => $fn(),
        );
    }
}
