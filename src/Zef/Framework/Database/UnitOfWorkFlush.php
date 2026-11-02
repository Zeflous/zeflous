<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The unit's FIFO execution walk: every queued operation, in queue
 * order, on the one connection the flush was handed -- the shared body
 * both flush spellings delegate to.
 *
 * @internal
 */
final class UnitOfWorkFlush
{
    /**
     * Runs the operations FIFO and answers how many executed. A failed
     * operation stops the walk and propagates.
     *
     * @param list<callable(ConnectionInterface): void> $operations
     */
    public static function run(ConnectionInterface $connection, array $operations): int
    {
        $executed = 0;
        foreach ($operations as $operation) {
            $operation($connection);
            ++$executed;
        }

        return $executed;
    }
}
