<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\ReviewThread;

/**
 * @internal
 */
#[CoversClass(ReviewThread::class)]
final class ReviewThreadTest extends TestCase
{
    public function testUnresolvedOutdatedThreadIsAFinishedConversation(): void
    {
        $reviewThread = new ReviewThread('PRRT_1', false, true, 'src/A.php');

        self::assertTrue($reviewThread->isFinishedConversation());
    }

    public function testUnresolvedLiveThreadIsNotAFinishedConversation(): void
    {
        $reviewThread = new ReviewThread('PRRT_2', false, false, 'src/A.php');

        self::assertFalse($reviewThread->isFinishedConversation());
    }

    public function testResolvedOutdatedThreadIsNotAFinishedConversation(): void
    {
        $reviewThread = new ReviewThread('PRRT_3', true, true, 'src/A.php');

        self::assertFalse($reviewThread->isFinishedConversation());
    }

    public function testResolvedLiveThreadIsNotAFinishedConversation(): void
    {
        $reviewThread = new ReviewThread('PRRT_4', true, false, 'src/A.php');

        self::assertFalse($reviewThread->isFinishedConversation());
    }

    public function testCarriesItsIdentityAndPath(): void
    {
        $reviewThread = new ReviewThread('PRRT_5', false, true, 'src/B.php');

        self::assertSame('PRRT_5', $reviewThread->id);
        self::assertSame('src/B.php', $reviewThread->path);
    }
}
