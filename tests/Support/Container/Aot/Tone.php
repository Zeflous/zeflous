<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/** Fixture: an enum whose cases fold as constant expressions in the artifact. */
enum Tone
{
    case CALM;
    case LOUD;
}
