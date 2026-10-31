<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * Standard SQL transaction isolation levels.
 *
 * The string value is the exact SQL token used by
 * `SET TRANSACTION ISOLATION LEVEL <value>`. Adapter support varies --
 * MySQL/PostgreSQL accept every level, SQLite rejects the concept.
 */
enum IsolationLevel: string
{
    case ReadUncommitted = 'READ UNCOMMITTED';
    case ReadCommitted = 'READ COMMITTED';
    case RepeatableRead = 'REPEATABLE READ';
    case Serializable = 'SERIALIZABLE';
}
