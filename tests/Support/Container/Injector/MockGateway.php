<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Injector;

use Override;

/**
 * Fixture gateway implementation: the default alias's target.
 */
final readonly class MockGateway implements PaymentGateway
{
    #[Override]
    public function name(): string
    {
        return 'mock';
    }
}
