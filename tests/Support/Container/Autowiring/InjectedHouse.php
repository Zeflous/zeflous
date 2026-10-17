<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Framework\Container\Autowiring\Inject;

/** Fixture: the #[Inject] attribute names the binding, the type is not consulted. */
final class InjectedHouse
{
    public function __construct(
        #[Inject('app.clock')]
        public string $clock,
    ) {
    }
}
