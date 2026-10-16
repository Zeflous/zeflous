<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Contracts\ContainerException;

/**
 * Thrown when a service's resolution re-enters itself: the runtime
 * cycle guard ({@see CycleGuard}) the resolution stack answers with
 * (PSR-11 -- a resolution failure is an exception the caller can
 * catch, never a fatal the process cannot).
 *
 * The message carries the whole chain that closed the cycle, in the
 * walk order the resolution stack recorded it, in the same shape the
 * definitions graph's own compile-time detector reports its chains
 * ({@see ServiceDefinitionGraph}) -- the two guards are the same
 * discipline at the two times a cycle can surface: declared edges
 * before the boot resolves them, factory calls while it does.
 */
final class CircularDependencyException extends ContainerException
{
}
