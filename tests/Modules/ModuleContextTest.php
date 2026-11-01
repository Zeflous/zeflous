<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Modules\ModuleContext;

/**
 * The module context's read surface: the block answers through the array
 * itself and through the has/get conveniences, and an absent key answers
 * the caller's default -- the module's whole window on the boot, readonly
 * by construction.
 *
 * @internal
 */
#[CoversClass(ModuleContext::class)]
final class ModuleContextTest extends TestCase
{
    public function testTheContextAnswersItsIdentity(): void
    {
        $moduleContext = new ModuleContext('zef/payment', '^1.0', ['endpoint' => 'http://x']);

        self::assertSame('zef/payment', $moduleContext->name);
        self::assertSame('^1.0', $moduleContext->version);
        self::assertSame(['endpoint' => 'http://x'], $moduleContext->config);
    }

    public function testTheBlockAnswersThroughTheConveniences(): void
    {
        $moduleContext = new ModuleContext('zef/payment', '^1.0', ['endpoint' => 'http://x', 'retries' => 3]);

        self::assertTrue($moduleContext->has('endpoint'));
        self::assertSame('http://x', $moduleContext->get('endpoint'));
        self::assertSame(3, $moduleContext->get('retries'));
    }

    public function testAnAbsentKeyAnswersTheDefault(): void
    {
        $moduleContext = new ModuleContext('zef/payment', '^1.0', []);

        self::assertFalse($moduleContext->has('endpoint'));
        self::assertNull($moduleContext->get('endpoint'));
        self::assertSame(7, $moduleContext->get('endpoint', 7));
    }
}
