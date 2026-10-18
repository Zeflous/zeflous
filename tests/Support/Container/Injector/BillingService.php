<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Injector;

/**
 * Fixture consumer for the contextual-binding lane: the constructor takes
 * the contract, so an autowired declaration references the contract's own
 * identifier -- exactly the reference the binding rewrites.
 */
final readonly class BillingService
{
    public function __construct(
        public PaymentGateway $gateway,
    ) {
    }

    public function charge(): string
    {
        return 'charged:' . $this->gateway->name();
    }
}
