<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\TransactionException;

/**
 * The nested-transaction bookkeeping fold behind
 * {@see PdoTransactions}: the depth counter, the nesting ceiling it
 * refuses to climb past, and the level-zero misuse guard -- the ONE
 * place the level lives, while the verb fold beside it owns which
 * driver statement realises each transition.
 *
 * @internal
 */
final class PdoTransactionDepth
{
    private const int MAX_NESTING = 16;

    private int $level = 0;

    /**
     * The level only ever moves one step per verb, so the exact ceiling
     * spelling and the comparison agree on every reachable state -- the
     * 17th nested begin meets level 16.
     *
     * @throws TransactionException when the nesting ceiling would be exceeded
     */
    public function assertRoomForOneMore(): void
    {
        if ($this->level === self::MAX_NESTING) {
            throw new TransactionException(
                'Transaction nesting limit of ' . self::MAX_NESTING . ' exceeded.',
            );
        }
    }

    /**
     * @throws TransactionException when no transaction is open
     */
    public function assertOpen(string $verb): void
    {
        if ($this->level === 0) {
            throw new TransactionException($verb . ' called outside a transaction.');
        }
    }

    /**
     * Steps one level deeper (the caller has issued the statement that
     * justifies the step).
     */
    public function enter(): void
    {
        ++$this->level;
    }

    /**
     * Steps one level back (the caller has issued the statement that
     * justifies the step).
     */
    public function leave(): void
    {
        --$this->level;
    }

    /**
     * Current nesting depth: 0 = no transaction, 1 = real transaction,
     * 2+ = inside savepoints.
     */
    public function level(): int
    {
        return $this->level;
    }
}
