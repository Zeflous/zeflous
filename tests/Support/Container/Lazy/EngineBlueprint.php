<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Lazy;

/**
 * Fixture abstract class: a class-shape lazy declaration behind an
 * abstract class is refused at parse time (a native lazy proxy is an
 * instance of the class itself, and an abstract class has none).
 */
abstract class EngineBlueprint
{
}
