<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\HttpWireBody;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\Stream;

/**
 * The wire-dump family's body fold: the body verbatim within the
 * bound, truncated with the honest byte count beyond it, and the
 * bound itself refused when negative.
 *
 * @internal
 */
#[CoversClass(HttpWireBody::class)]
final class HttpWireBodyTest extends TestCase
{
    public function testABodyWithinTheBoundIsAnsweredVerbatim(): void
    {
        $response = new Response(200, [], 'pong');

        self::assertSame('pong', HttpWireBody::rendered($response->getBody(), 8));
    }

    public function testABodyAtExactlyTheBoundIsAnsweredVerbatim(): void
    {
        $response = new Response(200, [], '12345678');

        self::assertSame('12345678', HttpWireBody::rendered($response->getBody(), 8));
    }

    public function testABodyBeyondTheBoundIsTruncatedWithTheHonestCount(): void
    {
        $response = new Response(200, [], '0123456789A');

        self::assertSame(
            "01234567\n[3 of 11 bytes truncated]\n",
            HttpWireBody::rendered($response->getBody(), 8),
        );
    }

    public function testAnEmptyBodyAnswersEmpty(): void
    {
        $response = new Response(200);

        self::assertSame('', HttpWireBody::rendered($response->getBody(), 8));
    }

    public function testAZeroBoundTruncatesEveryNonEmptyBody(): void
    {
        $response = new Response(200, [], 'pong');

        self::assertSame(
            "\n[4 of 4 bytes truncated]\n",
            HttpWireBody::rendered($response->getBody(), 0),
        );
    }

    public function testANegativeBoundIsRefused(): void
    {
        $response = new Response(200, [], 'pong');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('The wire body bound must not be negative.');

        HttpWireBody::rendered($response->getBody(), -1);
    }

    public function testAMultibyteBodyIsMeasuredInBytes(): void
    {
        // Seven characters, eleven bytes: the '8bit' fold counts the
        // bytes, the unit the truncation notice names.
        $response = new Response(200, [], 'ünïcödé');

        self::assertSame(
            "ünïcö\n[3 of 11 bytes truncated]\n",
            HttpWireBody::rendered($response->getBody(), 8),
        );
    }

    public function testABodyIsReadFromItsStartWhateverTheStreamsOwnPosition(): void
    {
        $stream = Stream::fromString('pong');
        $stream->seek(2);

        self::assertSame('pong', HttpWireBody::rendered($stream, 8));
    }
}
