<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Lazy;

/**
 * Fixture enum: a class-shape lazy declaration behind an enum is
 * refused at parse time (a native lazy proxy is an instance of the
 * class itself, and an enum instance is not a proxyable service).
 */
enum EngineMode
{
    case CRUISE;
    case FULL;
}
