<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Override;
use Zef\Framework\Container\ParameterProviderInterface;

/** A provider answering one scalar, computed at the compile phase. */
final class VersionProvider implements ParameterProviderInterface
{
    #[Override]
    public function value(): mixed
    {
        return '2.9.0-zeflous';
    }
}
