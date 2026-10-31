<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

/**
 * The host's own service: its declarative definition references a
 * module-registered service as its argument -- the register-before-
 * definitions order's own proof.
 */
final readonly class HostGreeter
{
    public function __construct(
        private string $greeting,
    ) {
    }

    public function greeting(): string
    {
        return $this->greeting;
    }
}
