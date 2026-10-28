<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\WorkerPeerAddress;

/**
 * The peer-address fold, on its own terms: the engine's own literal
 * judgment, the bracket normalisation, and the one canonical lowercase
 * spelling every downstream reader compares against.
 *
 * @internal
 */
#[CoversClass(WorkerPeerAddress::class)]
final class WorkerPeerAddressTest extends TestCase
{
    #[DataProvider('provideThePeerAddressJudgementAnswersTheBareLiteralCases')]
    public function testThePeerAddressJudgementAnswersTheBareLiteral(string $given, string $expected): void
    {
        self::assertSame(
            $expected,
            WorkerPeerAddress::judged($given),
            'the address the request carries is the bare literal form',
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideThePeerAddressJudgementAnswersTheBareLiteralCases(): iterable
    {
        yield 'an IPv4 literal rides as itself' => ['192.0.2.10', '192.0.2.10'];

        yield 'an IPv6 literal rides bare' => ['2001:db8::1', '2001:db8::1'];

        yield 'a bracketed IPv6 literal normalises to bare' => ['[2001:db8::1]', '2001:db8::1'];

        yield 'the loopback IPv6 rides bare' => ['::1', '::1'];

        yield 'uppercase IPv6 letters fold to lowercase' => ['2001:DB8::1', '2001:db8::1'];

        yield 'an IPv4-mapped IPv6 literal stays its own spelling' => ['::ffff:192.0.2.1', '::ffff:192.0.2.1'];
    }

    #[DataProvider('provideAnAddressOutsideTheLiteralGrammarIsRefusedCases')]
    public function testAnAddressOutsideTheLiteralGrammarIsRefused(string $address): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Invalid worker remote address');

        WorkerPeerAddress::judged($address);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAnAddressOutsideTheLiteralGrammarIsRefusedCases(): iterable
    {
        yield 'a DNS name is not a peer literal' => ['worker.internal'];

        yield 'an empty string is not a literal (absence is the null spelling)' => [''];

        yield 'a partial bracket is not a literal' => ['[::1'];

        yield 'a trailing bracket alone is not a literal' => ['::1]'];

        yield 'a CIDR suffix is a network, not a peer' => ['192.0.2.0/24'];

        yield 'a port-bearing authority is an authority, not an address' => ['192.0.2.10:443'];
    }
}
