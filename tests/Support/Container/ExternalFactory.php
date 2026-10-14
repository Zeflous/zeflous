<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container;

/**
 * Fixture factory class: both the static and the instance method forms of
 * the factory definition shape (`ExternalFactory::make` as a static call,
 * `ExternalFactory::build` as an instance method on a container-resolved
 * service).
 */
final readonly class ExternalFactory
{
    public function __construct(
        private string $prefix = 'static',
    ) {
    }

    public static function make(string $subject): string
    {
        return 'made:' . $subject;
    }

    public function build(string $subject): string
    {
        return $this->prefix . ':' . $subject;
    }
}
