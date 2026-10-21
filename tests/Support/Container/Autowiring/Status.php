<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: an enum-typed defaulted parameter -- not birthable, so the default owns the plan. */
enum Status
{
    case ACTIVE;
    case ARCHIVED;
}
