<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Override;
use stdClass;
use Zef\Framework\Container\ParameterProviderInterface;

/** A provider whose answer is not injectable (an object). */
final class UninjectableAnswerProvider implements ParameterProviderInterface
{
    #[Override]
    public function value(): mixed
    {
        return new stdClass();
    }
}
