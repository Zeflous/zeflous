<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Zef\Framework\Exception\ZefException;

/**
 * Base exception for every database-lane failure.
 *
 * The lane never leaks raw driver exceptions to the caller: drivers are
 * mapped onto {@see ConnectionException} (connect/config phase),
 * {@see QueryException} (grammar refusal, prepare/execute phase) and
 * {@see TransactionException} (transaction misuse) so applications can
 * catch one stable typed family -- the same lane-local family posture
 * `ConfigException` and `JsonLogWriteException` carry. These are internal
 * failures: the error boundary renders them as server errors, never as a
 * hand-built database-shaped response.
 */
abstract class DatabaseException extends ZefException
{
}
