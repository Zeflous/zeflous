<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The migration runner refused a declaration or a rollback request it
 * cannot honour honestly (fail closed).
 *
 * Every ambiguity the runner meets -- a version that is not a 14-digit
 * timestamp, a name outside the 1..128-byte corridor, a version
 * registered twice, a lock TTL that is not positive (the Migrator's own
 * or a migration's override), a rollback of zero or more steps than
 * were applied, or a rollback of a version whose migration class is not
 * registered -- is raised through this class, the migration phase's own
 * member of the lane's typed family (the `ModuleException` posture for
 * declaration refusals). Runtime transaction/lock misuse stays
 * {@see TransactionException} and malformed bookkeeping rows stay
 * {@see QueryException}.
 */
final class MigrationException extends DatabaseException
{
}
