<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Override;
use stdClass;
use Zef\Framework\Container\ParameterProviderInterface;

/** A provider whose array answer carries a non-injectable element. */
final class UninjectableElementProvider implements ParameterProviderInterface
{
    /** @return list<mixed> */
    #[Override]
    public function value(): mixed
    {
        return ['fine', new stdClass()];
    }
}
