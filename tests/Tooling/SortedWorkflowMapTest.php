<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\SortedWorkflowMap;

/**
 * @internal
 */
#[CoversClass(SortedWorkflowMap::class)]
final class SortedWorkflowMapTest extends TestCase
{
    public function testAnswersTheMapSortedByFileName(): void
    {
        $sorted = SortedWorkflowMap::over([
            'zebra.yml' => 'name: Z',
            'alpha.yaml' => 'name: A',
            'middle.yml' => 'name: M',
        ]);

        self::assertSame(['alpha.yaml', 'middle.yml', 'zebra.yml'], array_keys($sorted));
        self::assertSame('name: A', $sorted['alpha.yaml']);
        self::assertSame('name: M', $sorted['middle.yml']);
        self::assertSame('name: Z', $sorted['zebra.yml']);
    }

    public function testAnswersAnEmptyMapAsItself(): void
    {
        self::assertSame([], SortedWorkflowMap::over([]));
    }

    public function testKeepsAnAlreadySortedMapStable(): void
    {
        $sorted = SortedWorkflowMap::over([
            'a.yml' => 'name: A',
            'b.yml' => 'name: B',
        ]);

        self::assertSame(['a.yml', 'b.yml'], array_keys($sorted));
    }
}
