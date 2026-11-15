<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Override;
use Zef\Framework\Container\ParameterProviderInterface;

/**
 * The #793 fixture: a provider whose answer is derived from the running
 * process's environment -- the runtime-derived answer class the audit
 * named, so two boots of the same declaration set can answer two
 * different values.
 */
final class RuntimeAnswerProvider implements ParameterProviderInterface
{
    public const string ANSWER_ENV = 'ZEF_RUNTIME_ANSWER';

    #[Override]
    public function value(): mixed
    {
        $answer = getenv(self::ANSWER_ENV);

        return \is_string($answer) && $answer !== '' ? $answer : 'unset';
    }
}
