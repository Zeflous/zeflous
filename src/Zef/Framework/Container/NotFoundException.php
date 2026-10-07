<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Psr\Container\NotFoundExceptionInterface;
use Zef\Framework\Contracts\ContainerException;

/**
 * Thrown when a service cannot be located by its identifier (PSR-11).
 */
final class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
