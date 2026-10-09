<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\ReviewThread;
use Zef\Framework\Tooling\ReviewThreadFactory;

/**
 * @internal
 */
#[CoversClass(ReviewThreadFactory::class)]
final class ReviewThreadFactoryTest extends TestCase
{
    public function testBuildsAThreadFromANode(): void
    {
        $reviewThread = ReviewThreadFactory::fromNode([
            'id' => 'PRRT_6',
            'isResolved' => false,
            'isOutdated' => true,
            'path' => 'src/C.php',
        ]);

        self::assertInstanceOf(ReviewThread::class, $reviewThread);
        self::assertSame('PRRT_6', $reviewThread->id);
        self::assertTrue($reviewThread->isFinishedConversation());
        self::assertSame('src/C.php', $reviewThread->path);
    }

    public function testDefaultsMissingFlagsAndPath(): void
    {
        $reviewThread = ReviewThreadFactory::fromNode(['id' => 'PRRT_7']);

        self::assertInstanceOf(ReviewThread::class, $reviewThread);
        self::assertFalse($reviewThread->isFinishedConversation());
        self::assertSame('', $reviewThread->path);
    }

    public function testRejectsANonArrayNode(): void
    {
        self::assertNull(ReviewThreadFactory::fromNode('not-an-object'));
    }

    public function testRejectsAMissingId(): void
    {
        self::assertNull(ReviewThreadFactory::fromNode(['isResolved' => false, 'isOutdated' => true]));
    }

    public function testRejectsAnEmptyId(): void
    {
        self::assertNull(ReviewThreadFactory::fromNode(['id' => '', 'isResolved' => false, 'isOutdated' => true]));
    }

    public function testRejectsANonStringId(): void
    {
        self::assertNull(ReviewThreadFactory::fromNode(['id' => 123, 'isResolved' => false, 'isOutdated' => true]));
    }

    public function testIgnoresANonStringPath(): void
    {
        $reviewThread = ReviewThreadFactory::fromNode(['id' => 'PRRT_8', 'path' => 42]);

        self::assertInstanceOf(ReviewThread::class, $reviewThread);
        self::assertSame('', $reviewThread->path);
    }
}
