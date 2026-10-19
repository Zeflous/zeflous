<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Namespaces;

/**
 * The namespace lane's fixture service: a labelled product every shape
 * in the lane's tests constructs -- the declarative definition, the
 * fallback factory, and the prefix enumeration all answer it.
 */
final readonly class NamespacedService
{
    public function __construct(
        public string $label,
    ) {
    }
}
