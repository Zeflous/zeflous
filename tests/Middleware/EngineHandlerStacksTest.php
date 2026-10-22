<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Closure;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;
use Zef\Framework\Middleware\EngineHandlerStacks;

/**
 * @internal
 */
#[CoversClass(EngineHandlerStacks::class)]
final class EngineHandlerStacksTest extends TestCase
{
    /** @var Closure(int, string, string, int): bool */
    private Closure $errorHandler;

    /** @var Closure(Throwable): void */
    private Closure $exceptionHandler;

    #[Override]
    protected function setUp(): void
    {
        $this->errorHandler = static fn (int $severity, string $message, string $file, int $line): bool => true;
        $this->exceptionHandler = static function (Throwable $throwable): void {
        };
    }

    public function testTheStacksTheCallerPushedAreTheStacksItStillOwns(): void
    {
        set_error_handler($this->errorHandler);
        set_exception_handler($this->exceptionHandler);

        try {
            self::assertTrue(EngineHandlerStacks::toppedBy($this->errorHandler, $this->exceptionHandler));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testAForeignHandlerOnTopRefusesTheOwnership(): void
    {
        set_error_handler($this->errorHandler);
        set_exception_handler($this->exceptionHandler);
        set_error_handler(static fn (int $severity, string $message, string $file, int $line): bool => false);

        try {
            self::assertFalse(EngineHandlerStacks::toppedBy($this->errorHandler, $this->exceptionHandler));
        } finally {
            restore_error_handler();
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testAForeignExceptionHandlerOnTopRefusesTheOwnership(): void
    {
        set_error_handler($this->errorHandler);
        set_exception_handler($this->exceptionHandler);
        set_exception_handler(static function (Throwable $throwable): void {
        });

        try {
            self::assertFalse(EngineHandlerStacks::toppedBy($this->errorHandler, $this->exceptionHandler));
        } finally {
            restore_exception_handler();
            restore_exception_handler();
            restore_error_handler();
        }
    }

    public function testTheProbeLeavesTheStacksExactlyAsItFoundThem(): void
    {
        set_error_handler($this->errorHandler);
        set_exception_handler($this->exceptionHandler);

        try {
            EngineHandlerStacks::toppedBy($this->errorHandler, $this->exceptionHandler);

            // The probe pushed and popped its own throwaways: the
            // caller's handlers are still the tops.
            self::assertTrue(EngineHandlerStacks::toppedBy($this->errorHandler, $this->exceptionHandler));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
