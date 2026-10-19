<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Decorators;

use Zef\Framework\Container\ServiceDefinition;

/**
 * A hand-built definition of none of the four declarative shapes --
 * the re-homing's misuse guard exists for exactly this, and this
 * fixture is the shape that trips it.
 */
final readonly class ShapelessDefinition extends ServiceDefinition
{
}
