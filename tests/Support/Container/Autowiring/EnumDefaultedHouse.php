<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: the enum-case default the plan bakes verbatim. */
final class EnumDefaultedHouse
{
    public function __construct(
        public Status $status = Status::ACTIVE,
    ) {
    }
}
