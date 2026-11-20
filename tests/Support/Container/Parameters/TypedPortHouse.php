<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/**
 * The env-shaped mismatch's own consumer (issue #800's finding 4): a
 * typed parameter whose address carries the raw string the environment
 * layer hands over, the exact shape the plan's own compatibility check
 * answers.
 */
final class TypedPortHouse
{
    public function __construct(
        #[Value('db.port')]
        public int $port,
    ) {
    }
}
