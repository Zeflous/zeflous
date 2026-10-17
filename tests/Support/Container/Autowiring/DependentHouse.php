<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: a declaration whose typed parameter births SimpleDep under its own name. */
final class DependentHouse
{
    public function __construct(
        public SimpleDep $dep,
        public string $label = 'lbl',
    ) {
    }
}
