<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Override;
use Zef\Framework\Container\ParameterProviderInterface;

/** A provider whose optional constructor parameters are defaulted. */
final readonly class OptionalProvider implements ParameterProviderInterface
{
    public function __construct(private int $margin = 4)
    {
    }

    #[Override]
    public function value(): mixed
    {
        return $this->margin + 1;
    }
}
