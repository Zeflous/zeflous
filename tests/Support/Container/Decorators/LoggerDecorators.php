<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Decorators;

/**
 * The factory-shaped decorator: a static factory whose first argument
 * is the layer it wraps (the decorator entry's reserved '@inner'
 * reference), answering a fixed-prefix wrapper.
 */
final class LoggerDecorators
{
    public static function timing(LineLogger $lineLogger): LineLogger
    {
        return new PrefixedLogger($lineLogger, 'timing');
    }
}
