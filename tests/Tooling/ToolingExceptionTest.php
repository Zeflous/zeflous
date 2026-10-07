<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Exception\ZefException;
use Zef\Framework\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(ToolingException::class)]
#[CoversClass(ZefException::class)]
final class ToolingExceptionTest extends TestCase
{
    public function testCarriesTheMessage(): void
    {
        self::assertSame('boom', new ToolingException('boom')->getMessage());
    }

    public function testIsCatchableAsAZefException(): void
    {
        try {
            throw new ToolingException('boom');
        } catch (ZefException $zefException) {
            self::assertSame('boom', $zefException->getMessage());
        }
    }
}
