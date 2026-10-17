<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Framework\Container\Autowiring\Inject;

/** Fixture: the name-keyed declaration outranks the attribute on the same parameter. */
final class NamedWinsHouse
{
    public function __construct(
        #[Inject('app.clock')]
        public string $clock,
        public string $channel = 'default',
    ) {
    }
}
