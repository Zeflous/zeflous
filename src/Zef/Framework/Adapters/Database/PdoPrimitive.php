<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Closure;
use PDOException;
use Zef\Framework\Database\TransactionException;

/**
 * One driver primitive (beginTransaction, commit, rollBack), its failure
 * mapped onto the typed transaction family with the verb named -- the
 * begin/commit/rollback twin of {@see PdoIsolation}'s administrative
 * statement mapping.
 *
 * @internal
 */
final class PdoPrimitive
{
    /**
     * @param Closure(): bool $action the driver primitive to run
     *
     * @throws TransactionException when the driver refuses the primitive
     */
    public static function run(Closure $action, string $failure): void
    {
        try {
            $action();
        } catch (PDOException $pdoException) {
            throw new TransactionException(
                'Failed to ' . $failure . ': ' . $pdoException->getMessage(),
                0,
                $pdoException,
            );
        }
    }
}
