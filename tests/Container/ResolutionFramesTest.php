<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\RequestScopeException;
use Zef\Framework\Container\ResolutionFrames;

/**
 * The frame ledger's own battery: the innermost-captor census read
 * through the ledger's own public surface, the honest close a refused
 * or thrown factory still owes, and the captive guard the request lane
 * reads through it.
 *
 * @internal
 */
#[CoversClass(ResolutionFrames::class)]
final class ResolutionFramesTest extends TestCase
{
    public function testNoOpenFrameAnswersNoCaptor(): void
    {
        $resolutionFrames = new ResolutionFrames();

        self::assertNull($resolutionFrames->capturingSingleton());
    }

    public function testAnOpenFrameNamesItsOwnSingleton(): void
    {
        $resolutionFrames = new ResolutionFrames();

        $captured = $resolutionFrames->withinSingleton(
            'app.singleton',
            static fn (): ?string => $resolutionFrames->capturingSingleton(),
        );

        self::assertSame('app.singleton', $captured);
        self::assertNull(
            $resolutionFrames->capturingSingleton(),
            'the frame closes in finally, so no stale captor survives it',
        );
    }

    public function testNestedFramesNameTheInnermostCaptor(): void
    {
        $resolutionFrames = new ResolutionFrames();

        $innermost = $resolutionFrames->withinSingleton(
            'app.outer',
            static fn (): mixed => $resolutionFrames->withinSingleton(
                'app.inner',
                static fn (): ?string => $resolutionFrames->capturingSingleton(),
            ),
        );

        self::assertSame('app.inner', $innermost, 'the innermost frame is the memo that will hold the instance');
        self::assertNull($resolutionFrames->capturingSingleton());
    }

    public function testAThrownFactoryStillClosesItsFrame(): void
    {
        $resolutionFrames = new ResolutionFrames();

        try {
            $resolutionFrames->withinSingleton(
                'app.singleton',
                static function (): never {
                    throw new RequestScopeException('The factory refused.');
                },
            );
            self::fail('The factory refusal must propagate.');
        } catch (RequestScopeException) {
        }

        self::assertNull($resolutionFrames->capturingSingleton());
    }

    public function testTheCaptiveGuardPassesOutsideAFrameAndRefusesInsideOne(): void
    {
        $resolutionFrames = new ResolutionFrames();

        self::assertSame('app.request', $resolutionFrames->assertedNotCaptured('app.request'));

        try {
            $resolutionFrames->withinSingleton(
                'app.singleton',
                static fn (): string => $resolutionFrames->assertedNotCaptured('app.request'),
            );
            self::fail('A request-scoped id beneath an open singleton frame must refuse.');
        } catch (RequestScopeException $requestScopeException) {
            self::assertStringContainsString(
                "Service 'app.singleton' (singleton) is resolving",
                $requestScopeException->getMessage(),
            );
        }
    }
}
