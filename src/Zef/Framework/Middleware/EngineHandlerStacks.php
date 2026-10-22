<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Closure;
use Throwable;

/**
 * The engine's two handler stacks, observed at their tops -- the one
 * honest way to read them, because PHP keeps no read-only window
 * onto either ({@see ProcessErrorBoundary}'s restore is the caller).
 *
 * The probe pushes a throwaway handler of its own onto each stack,
 * reads the previous top the engine answers, and pops the throwaway
 * back off: the exact instance the boundary pushed, or a foreigner's
 * -- the verdict the boundary's restore contract enforces before it
 * pops anything.
 */
final class EngineHandlerStacks
{
    /**
     * Whether the engine's two handler stacks are still topped by the
     * exact instances the caller pushed.
     *
     * @param Closure(int, string, string, int): bool $errorHandler     the error handler the caller pushed
     * @param Closure(Throwable): void                $exceptionHandler the exception handler the caller pushed
     */
    public static function toppedBy(Closure $errorHandler, Closure $exceptionHandler): bool
    {
        // @infection-ignore-all The probe is pushed and popped without
        // ever being invoked -- the engine only answers the handler its
        // push would replace -- so the body's boolean literal is
        // unreachable by every test that can exist.
        $errorProbe = static fn (): bool => true;
        $currentError = set_error_handler($errorProbe);
        restore_error_handler();

        /**
         * The throwaway never runs: it exists only so the engine can be
         * asked for the handler its push would replace -- pushed and
         * popped immediately after. Its one statement is the honest
         * rethrow a probe that somehow ran would owe.
         *
         * @infection-ignore-all Same unreachability: the probe is popped
         * before any throwable could ever dispatch into it, so the
         * rethrow's removal is invisible to every test that can exist.
         */
        $exceptionProbe = static fn (Throwable $throwable): never => throw $throwable;

        $currentException = set_exception_handler($exceptionProbe);
        restore_exception_handler();

        return $currentError === $errorHandler && $currentException === $exceptionHandler;
    }
}
