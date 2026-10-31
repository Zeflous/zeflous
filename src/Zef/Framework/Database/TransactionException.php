<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The transaction primitives were misused: commit or rollback outside a
 * transaction, an isolation request on a nested savepoint, or the
 * nesting ceiling exceeded.
 */
final class TransactionException extends DatabaseException
{
}
