<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\WorkerConnection;

/**
 * The connection-info injector, on its own terms: the port's corridor
 * and the precise entries a peer connection owns -- including the
 * absence a runtime that cannot name its peer contributes.
 *
 * @internal
 */
#[CoversClass(WorkerConnection::class)]
final class WorkerConnectionTest extends TestCase
{
    #[DataProvider('provideAPortOutsideItsCorridorIsRefusedCases')]
    public function testAPortOutsideItsCorridorIsRefused(int $port): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains("Invalid worker remote port {$port}");

        WorkerConnection::assertPeerPort($port);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideAPortOutsideItsCorridorIsRefusedCases(): iterable
    {
        yield 'the zero port' => [0];

        yield 'a negative port' => [-1];

        yield 'one past the ceiling' => [65536];
    }

    public function testTheCorridorsOwnEdgesHold(): void
    {
        WorkerConnection::assertPeerPort(1);
        WorkerConnection::assertPeerPort(65535);

        $this->expectNotToPerformAssertions();
    }

    public function testThePeerPairAnswersBothEntries(): void
    {
        self::assertSame(
            ['REMOTE_ADDR' => '192.0.2.10', 'REMOTE_PORT' => 54123],
            WorkerConnection::entries('192.0.2.10', 54123),
            'the precise injection is the two entries a peer connection owns',
        );
    }

    public function testAnAddressWithoutItsPortAnswersTheAddressEntry(): void
    {
        self::assertSame(
            ['REMOTE_ADDR' => '192.0.2.10'],
            WorkerConnection::entries('192.0.2.10', null),
            'a runtime that cannot name the port still names the peer',
        );
    }

    public function testAnUnnamedPeerContributesNoEntry(): void
    {
        self::assertSame(
            [],
            WorkerConnection::entries(null, null),
            'absence stays absence -- no placeholder entry is invented',
        );
    }

    public function testAPortWithoutItsAddressDescribesNoPeer(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('without its address describes no peer');

        WorkerConnection::entries(null, 54123);
    }
}
