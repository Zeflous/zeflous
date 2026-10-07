<?php

declare(strict_types=1);

namespace Zef\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Exception\InvariantViolationException;
use Zef\Framework\Exception\ZefException;

/**
 * @internal
 */
#[CoversClass(InvariantViolationException::class)]
final class InvariantViolationExceptionTest extends TestCase
{
    public function testCarriesTheMessage(): void
    {
        self::assertSame('boom', new InvariantViolationException('boom')->getMessage());
    }

    public function testIsCatchableAsAZefException(): void
    {
        try {
            throw new InvariantViolationException('boom');
        } catch (ZefException $zefException) {
            self::assertSame('boom', $zefException->getMessage());
        }
    }
}
