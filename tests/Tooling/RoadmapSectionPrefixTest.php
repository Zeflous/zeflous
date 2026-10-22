<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\RoadmapSectionPrefix;

/**
 * @internal
 */
#[CoversClass(RoadmapSectionPrefix::class)]
final class RoadmapSectionPrefixTest extends TestCase
{
    public function testMatchesASectionThatPrefixesTheArea(): void
    {
        self::assertSame(
            'httplayer',
            RoadmapSectionPrefix::matching('HTTP Layer (Zero-Library Shim)', ['httplayer' => 3]),
        );
    }

    public function testReturnsNullWhenNoSectionMatches(): void
    {
        self::assertNull(RoadmapSectionPrefix::matching('Widgets', ['httplayer' => 3]));
    }

    public function testIgnoresAnEmptySectionKey(): void
    {
        self::assertNull(RoadmapSectionPrefix::matching('Widgets', ['' => 3]));
    }
}
