<?php

declare(strict_types=1);

namespace Zef\Test\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\DotListWriter;

/**
 * @internal
 */
#[CoversClass(DotListWriter::class)]
final class DotListWriterTest extends TestCase
{
    public function testAppendAddsTheValueToTheEndOfAList(): void
    {
        self::assertSame(
            ['middleware' => ['auth', 'cors', 'gzip']],
            DotListWriter::appendAt(['middleware' => ['auth', 'cors']], ['middleware'], 'gzip'),
        );
    }

    public function testAppendToAnEmptyListYieldsASingleElementList(): void
    {
        self::assertSame(
            ['middleware' => ['gzip']],
            DotListWriter::appendAt(['middleware' => []], ['middleware'], 'gzip'),
        );
    }

    public function testAppendToASingleElementListKeepsTheOrder(): void
    {
        self::assertSame(
            ['queue' => ['default', 'high']],
            DotListWriter::appendAt(['queue' => ['default']], ['queue'], 'high'),
        );
    }

    public function testAppendToANestedList(): void
    {
        self::assertSame(
            ['users' => [['roles' => ['admin', 'dev']]]],
            DotListWriter::appendAt(
                ['users' => [['roles' => ['admin']]]],
                ['users', '0', 'roles'],
                'dev',
            ),
        );
    }

    public function testPrependAddsTheValueToTheFrontOfAList(): void
    {
        self::assertSame(
            ['middleware' => ['gzip', 'auth', 'cors']],
            DotListWriter::prependAt(['middleware' => ['auth', 'cors']], ['middleware'], 'gzip'),
        );
    }

    public function testPrependToAnEmptyListYieldsASingleElementList(): void
    {
        self::assertSame(
            ['middleware' => ['gzip']],
            DotListWriter::prependAt(['middleware' => []], ['middleware'], 'gzip'),
        );
    }

    public function testPrependToASingleElementListKeepsTheOrder(): void
    {
        self::assertSame(
            ['queue' => ['high', 'default']],
            DotListWriter::prependAt(['queue' => ['default']], ['queue'], 'high'),
        );
    }

    public function testPrependToANestedList(): void
    {
        self::assertSame(
            ['users' => [['roles' => ['dev', 'admin']]]],
            DotListWriter::prependAt(
                ['users' => [['roles' => ['admin']]]],
                ['users', '0', 'roles'],
                'dev',
            ),
        );
    }
}
