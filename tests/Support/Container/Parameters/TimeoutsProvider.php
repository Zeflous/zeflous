<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Override;
use Zef\Framework\Container\ParameterProviderInterface;

/** A provider answering one whole array, computed at the compile phase. */
final class TimeoutsProvider implements ParameterProviderInterface
{
    /** @return array<string, int> */
    #[Override]
    public function value(): mixed
    {
        return ['connect' => 5, 'read' => 30];
    }
}
