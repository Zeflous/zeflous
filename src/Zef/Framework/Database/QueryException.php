<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * A query failed: either the builder's own grammar refused the input
 * (construction time) or the driver refused to prepare or execute the
 * statement (run time, the original driver failure chained).
 */
final class QueryException extends DatabaseException
{
}
