<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Injector;

use Override;

/**
 * Fixture gateway implementation: the contextual binding's target.
 */
final readonly class StripeGateway implements PaymentGateway
{
    #[Override]
    public function name(): string
    {
        return 'stripe';
    }
}
