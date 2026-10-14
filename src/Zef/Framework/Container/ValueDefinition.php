<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The value shape: a raw scalar/array parameter registered as a service.
 *
 * Injectable configuration ("parameter global" in the roadmap's registration
 * area): strings, numbers, booleans, null and arrays travel through the
 * container like any other service, so factories and class arguments can
 * reference them uniformly.
 */
final readonly class ValueDefinition extends ServiceDefinition
{
    public function __construct(
        string $id,
        public mixed $value,
    ) {
        parent::__construct($id);
    }
}
