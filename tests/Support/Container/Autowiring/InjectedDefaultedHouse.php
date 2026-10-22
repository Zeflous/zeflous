<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Framework\Container\Autowiring\Inject;

/** Fixture: an explicit #[Inject] naming nothing, over a defaulted parameter. */
final class InjectedDefaultedHouse
{
    public function __construct(
        #[Inject('app.missing')]
        public ?LoggerInterface $logger = null,
    ) {
    }
}
