<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Injector;

/**
 * Fixture factory product for the method-call injection lane's factory
 * arm: the static `make` builds it, and the post-construction `setOrigin`
 * records the injected value -- proving the calls apply to a factory's
 * product, not only to a constructor's.
 */
final class FactoryProduct
{
    private string $origin = 'unset';

    public function __construct(
        public string $subject,
    ) {
    }

    public static function make(string $subject): self
    {
        return new self($subject);
    }

    public function setOrigin(string $origin): void
    {
        $this->origin = $origin;
    }

    public function describe(): string
    {
        return $this->origin . ':' . $this->subject;
    }
}
