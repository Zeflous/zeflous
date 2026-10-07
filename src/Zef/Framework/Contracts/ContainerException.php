<?php

declare(strict_types=1);

namespace Zef\Framework\Contracts;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * Base container error raised while resolving a service (PSR-11).
 *
 * Kept in the Contracts layer so that both the container and the kernel can
 * depend on the error abstraction without depending on each other.
 */
abstract class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}
