<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Override;
use Zef\Framework\Container\ParameterProviderInterface;

/** A provider demanding constructor arguments the lane cannot supply. */
final readonly class NeedyProvider implements ParameterProviderInterface
{
    public function __construct(private string $dependency)
    {
    }

    #[Override]
    public function value(): mixed
    {
        return $this->dependency;
    }
}
