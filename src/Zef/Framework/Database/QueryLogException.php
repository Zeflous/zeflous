<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The query-log family's own refusal: the threshold corridor's halt,
 * landing wherever an out-of-corridor bar reaches the policy's
 * construction ({@see QueryLogBounds}) -- the config-driven walk that
 * reads a mistyped `database.query_log threshold_ms` and the host
 * hand-spelling the same nonsense value get the one named halt, the
 * same sub-family posture the migration lane's own TTL corridor
 * carries ({@see MigrationTtl}'s `MigrationException`).
 */
final class QueryLogException extends DatabaseException
{
}
