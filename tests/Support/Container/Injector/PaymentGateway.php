<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Injector;

/**
 * Fixture contract for the contextual-binding lane: two implementations
 * exist, and the consumer under test receives whichever the binding (or
 * the default alias) hands it.
 */
interface PaymentGateway
{
    public function name(): string;
}
