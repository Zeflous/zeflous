<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container;

use Zef\Framework\Container\ServiceDefinition;

/**
 * A hand-built definition shape the compiler does not know -- the fixture
 * that proves the compiler's default arm fails closed on any future
 * subclass of the definition hierarchy instead of guessing.
 */
final readonly class UnsupportedDefinition extends ServiceDefinition
{
}
