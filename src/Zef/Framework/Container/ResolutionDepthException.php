<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Contracts\ContainerException;

/**
 * A resolution chain deeper than {@see CycleGuard}'s budget of open
 * frames.
 *
 * Not a cycle -- a cycle is refused with the walked chain that closed
 * it ({@see CircularDependencyException}) -- but the pathological
 * near-cousin: a chain so long the budget cannot believe it is honest.
 * The budget is the runtime half of the boot graph's own two-time
 * discipline: the graph proves the declared edges acyclic before a
 * single service resolves, this guard keeps the factory-call stack
 * bounded while they do.
 */
final class ResolutionDepthException extends ContainerException
{
}
