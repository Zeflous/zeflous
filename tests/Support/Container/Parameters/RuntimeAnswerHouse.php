<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/**
 * The #793 fixture's consumer: a house whose parameter the provider lane
 * computes, so the value service the AOT artifact bakes is the provider's
 * dump-time answer -- the staleness the digest must now see.
 */
final readonly class RuntimeAnswerHouse
{
    public function __construct(
        #[Value('runtime.answer')]
        public string $answer,
    ) {
    }
}
