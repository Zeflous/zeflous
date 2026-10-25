<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\ListenerPriority;

/**
 * Pins the priority's one rule: any integer is a standing, and the
 * one value PHP would silently coerce -- a boolean -- is refused
 * with its own type named, because `true` as a priority is a
 * typo's way of ordering a listener at standing one.
 *
 * @internal
 */
#[CoversClass(ListenerPriority::class)]
final class ListenerPriorityTest extends TestCase
{
    public function testAnyIntegerIsAStanding(): void
    {
        self::assertSame(0, new ListenerPriority(0)->standing());
        self::assertSame(100, new ListenerPriority(100)->standing());
        self::assertSame(-100, new ListenerPriority(-100)->standing());
        self::assertSame(
            \PHP_INT_MAX,
            new ListenerPriority(\PHP_INT_MAX)->standing(),
        );
        self::assertSame(
            \PHP_INT_MIN,
            new ListenerPriority(\PHP_INT_MIN)->standing(),
        );
    }

    public function testABooleanIsRefused(): void
    {
        try {
            new ListenerPriority(true);
            self::fail('The boolean priority was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A listener priority must be an integer, got bool.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAFloatIsRefused(): void
    {
        try {
            new ListenerPriority(1.5);
            self::fail('The float priority was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A listener priority must be an integer, got float.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAStringIsRefused(): void
    {
        try {
            new ListenerPriority('100');
            self::fail('The string priority was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A listener priority must be an integer, got string.',
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
