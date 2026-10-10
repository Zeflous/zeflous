<?php

declare(strict_types=1);

namespace Zef\Test\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\DotRemover;

/**
 * @internal
 */
#[CoversClass(DotRemover::class)]
final class DotRemoverTest extends TestCase
{
    public function testRemoveDeletesALeafAndKeepsTheParentArray(): void
    {
        // Siblings at every level on purpose: killing the array-slicing
        // mutant requires the data on each return path to hold more than one
        // entry, otherwise the sliced result is indistinguishable.
        self::assertSame(
            ['app' => 'zef', 'database' => ['port' => 3306, 'user' => 'root']],
            DotRemover::remove(
                ['app' => 'zef', 'database' => ['host' => 'localhost', 'port' => 3306, 'user' => 'root']],
                ['database', 'host'],
            ),
        );
    }

    public function testRemoveDeletesAWholeBranch(): void
    {
        self::assertSame(
            ['app' => 'zef', 'cache' => true],
            DotRemover::remove(
                ['app' => 'zef', 'cache' => true, 'database' => ['host' => 'localhost']],
                ['database'],
            ),
        );
    }

    public function testRemoveOfAMissingKeyLeavesTheDataUnchanged(): void
    {
        self::assertSame(
            ['app' => 'zef', 'debug' => true],
            DotRemover::remove(['app' => 'zef', 'debug' => true], ['missing']),
        );
    }

    public function testRemoveOfAMissingDeepPathLeavesTheDataUnchanged(): void
    {
        $data = ['database' => ['host' => 'localhost', 'port' => 3306]];

        self::assertSame(
            $data,
            DotRemover::remove($data, ['database', 'mysql', 'host']),
        );
    }

    public function testRemoveStopsAtAScalarIntermediate(): void
    {
        self::assertSame(
            ['app' => 'zef', 'database' => 'mysql'],
            DotRemover::remove(
                ['app' => 'zef', 'database' => 'mysql'],
                ['database', 'host', 'port'],
            ),
        );
    }
}
