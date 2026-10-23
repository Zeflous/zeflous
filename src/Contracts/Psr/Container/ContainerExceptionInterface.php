<?php

declare(strict_types=1);

namespace Psr\Container;

use Throwable;

/**
 * Base interface representing a generic exception in a container.
 *
 * Vendored by the ZEF zero-library shim; adapted from PSR-11 v2 upstream
 * with the house preamble (strict_types), matching the vendored PSR-3 and
 * PSR-7/15 contracts' own posture. An interface carries no executable code,
 * so the declare is policy consistency, not a behaviour change.
 */
interface ContainerExceptionInterface extends Throwable
{
}
