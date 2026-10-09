<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\ReviewThreadResolution;

/**
 * @internal
 */
#[CoversClass(ReviewThreadResolution::class)]
final class ReviewThreadResolutionTest extends TestCase
{
    public function testCarriesTheCounts(): void
    {
        $reviewThreadResolution = new ReviewThreadResolution(5, 3, 1);

        self::assertSame(5, $reviewThreadResolution->total);
        self::assertSame(3, $reviewThreadResolution->resolved);
        self::assertSame(1, $reviewThreadResolution->failed);
    }

    public function testMessageSummarisesTheRun(): void
    {
        $reviewThreadResolution = new ReviewThreadResolution(5, 3, 1);

        self::assertSame(
            'Review threads: 5 total, 3 finished conversation(s) resolved, 1 failed.',
            $reviewThreadResolution->message(),
        );
    }

    public function testMessageForAnEmptyRun(): void
    {
        $reviewThreadResolution = new ReviewThreadResolution(0, 0, 0);

        self::assertSame(
            'Review threads: 0 total, 0 finished conversation(s) resolved, 0 failed.',
            $reviewThreadResolution->message(),
        );
    }
}
