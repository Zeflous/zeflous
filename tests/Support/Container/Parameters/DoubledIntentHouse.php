<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Inject;
use Zef\Framework\Container\Autowiring\Value;

/** One parameter cannot carry two binding intents. */
final readonly class DoubledIntentHouse
{
    public function __construct(
        #[Inject('app.clock')]
        #[Value('app.clock.value')]
        public string $clock,
    ) {
    }
}
