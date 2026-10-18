<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Injector;

/**
 * Fixture consumer whose dependency arrives through a method-call
 * injection rather than the constructor: the contextual rewrite must
 * cover the call's argument references too, not only the constructor's.
 */
final class ReportingService
{
    public const string NO_GATEWAY = 'none';

    private ?PaymentGateway $paymentGateway = null;

    public function __construct()
    {
        // Deliberately empty: the gateway arrives through the setter the
        // method-call injection lane exercises, never through construction.
    }

    public function setGateway(PaymentGateway $paymentGateway): void
    {
        $this->paymentGateway = $paymentGateway;
    }

    public function gatewayName(): string
    {
        return $this->paymentGateway?->name() ?? self::NO_GATEWAY;
    }
}
