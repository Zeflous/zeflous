<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Framework\Container\Autowiring\Inject;

/** Fixture: the #[Inject] attribute naming a class -- the birth arm behind the attribute. */
final class InjectedDepHouse
{
    public function __construct(
        #[Inject(SimpleDep::class)]
        public SimpleDep $dep,
    ) {
    }
}
