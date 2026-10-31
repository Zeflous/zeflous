<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * A connection could not be established or its configuration is
 * malformed: every {@see ConnectionConfig} refusal and every driver
 * connect failure lands here, at construction time or on first use.
 */
final class ConnectionException extends DatabaseException
{
}
