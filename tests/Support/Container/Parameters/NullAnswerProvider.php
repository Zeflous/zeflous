<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Override;
use Zef\Framework\Container\ParameterProviderInterface;

/** A provider answering the explicit null (honest data, not absence). */
final class NullAnswerProvider implements ParameterProviderInterface
{
    #[Override]
    public function value(): mixed
    {
        return null;
    }
}
