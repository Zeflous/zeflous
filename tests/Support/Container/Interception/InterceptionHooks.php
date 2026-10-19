<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

use Zef\Framework\Container\ResolvedListener;
use Zef\Framework\Container\ServiceInterceptor;

/**
 * The factory-shaped entry's carriers: a static factory spelling the
 * plain builder form (the log arriving as the ordinary reference
 * grammar's answer), an instance-method factory (the registered
 * service precedence -- a non-static method only answers through the
 * resolved instance), and the non-object refusal carrier.
 */
final class InterceptionHooks
{
    public static function counting(InterceptionLog $interceptionLog): ServiceInterceptor
    {
        return new RecordingInterceptor('hook', $interceptionLog);
    }

    /**
     * The instance-method form: only answerable through a registered
     * service instance (a static call of a non-static method is the
     * engine's own error), so the materialisation's precedence -- a
     * registered target resolved and called as an instance method --
     * is the one path that reaches it.
     */
    public function countingInstance(InterceptionLog $interceptionLog): ServiceInterceptor
    {
        return new RecordingInterceptor('instance-hook', $interceptionLog);
    }

    public static function replacing(InterceptionLog $interceptionLog): ResolvedListener
    {
        return new ReplacingResolvedListener($interceptionLog);
    }

    /**
     * The non-object answer: a factory-shaped entry whose builder
     * answers a string -- the built-not-an-object refusal's carrier.
     */
    public static function notAnObject(): string
    {
        return 'not an object';
    }
}
