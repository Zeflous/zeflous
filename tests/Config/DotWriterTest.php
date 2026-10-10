<?php

declare(strict_types=1);

namespace Zef\Test\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\DotWriter;

/**
 * @internal
 */
#[CoversClass(DotWriter::class)]
final class DotWriterTest extends TestCase
{
    public function testSetWritesAFlatLeaf(): void
    {
        self::assertSame(['app' => 'new'], DotWriter::set(['app' => 'old'], ['app'], 'new'));
    }

    public function testSetCreatesMissingIntermediateArrays(): void
    {
        self::assertSame(
            ['database' => ['mysql' => ['host' => 'localhost']]],
            DotWriter::set([], ['database', 'mysql', 'host'], 'localhost'),
        );
    }

    public function testSetReplacesAScalarIntermediateWithAnArray(): void
    {
        self::assertSame(
            ['database' => ['host' => 'localhost']],
            DotWriter::set(['database' => 'mysql'], ['database', 'host'], 'localhost'),
        );
    }

    public function testSetReplacesAnExplicitNullIntermediateWithAnArray(): void
    {
        self::assertSame(
            ['cache' => ['driver' => 'array']],
            DotWriter::set(['cache' => null], ['cache', 'driver'], 'array'),
        );
    }

    public function testSetOverwritesAScalarLeafWithAnArray(): void
    {
        self::assertSame(
            ['app' => ['name' => 'zef']],
            DotWriter::set(['app' => 'scalar'], ['app'], ['name' => 'zef']),
        );
    }

    public function testSetOverwritesAnArrayLeafWithAScalar(): void
    {
        self::assertSame(
            ['app' => 'scalar'],
            DotWriter::set(['app' => ['name' => 'zef']], ['app'], 'scalar'),
        );
    }

    public function testSetPreservesSiblingBranches(): void
    {
        $data = ['database' => ['host' => 'localhost'], 'app' => ['name' => 'zef']];

        self::assertSame(
            ['database' => ['host' => 'localhost', 'port' => 3306], 'app' => ['name' => 'zef']],
            DotWriter::set($data, ['database', 'port'], 3306),
        );
    }

    public function testSetAtAMissingNumericOffsetCreatesItWithoutReindexing(): void
    {
        self::assertSame(
            ['users' => [0 => ['name' => 'ada'], 2 => ['name' => 'bob']]],
            DotWriter::set(['users' => [['name' => 'ada']]], ['users', '2', 'name'], 'bob'),
        );
    }

    public function testSetAtAnExistingNumericOffsetFollowsTheListEntry(): void
    {
        self::assertSame(
            ['users' => [['name' => 'ada'], ['name' => 'grace']]],
            DotWriter::set(
                ['users' => [['name' => 'ada'], ['name' => 'bob']]],
                ['users', '1', 'name'],
                'grace',
            ),
        );
    }
}
