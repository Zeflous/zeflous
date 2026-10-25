<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpWireResponse;
use Zef\Framework\Http\Response;

/**
 * The wire-dump family's response half: the response block over its
 * own status line -- version, code, and the reason phrase only when
 * the response carries one (an unregistered code's line ends at the
 * code, never a dangling space).
 *
 * @internal
 */
#[CoversClass(HttpWireResponse::class)]
final class HttpWireResponseTest extends TestCase
{
    public function testAResponseWithHeadersAndABodyAnswersItsFullWireBlock(): void
    {
        $response = new Response(200, ['Content-Type' => 'text/plain'], 'pong');

        self::assertSame(
            "HTTP/1.1 200 OK\r\n"
            . "Content-Type: text/plain\r\n"
            . "\r\n"
            . 'pong',
            HttpWireResponse::rendered($response, 64),
        );
    }

    public function testABodylessResponseEndsAtTheClosingPair(): void
    {
        $response = new Response(204, ['X-Nothing' => 'here']);

        self::assertSame(
            "HTTP/1.1 204 No Content\r\n"
            . "X-Nothing: here\r\n"
            . "\r\n",
            HttpWireResponse::rendered($response, 64),
        );
    }

    public function testAHeaderlessResponseAnswersItsStatusLineThenTheClosingPair(): void
    {
        $response = new Response(200);

        self::assertSame(
            "HTTP/1.1 200 OK\r\n"
            . "\r\n",
            HttpWireResponse::rendered($response, 64),
        );
    }

    public function testAnUnregisteredCodeCarriesNoReasonPhraseAndNoDanglingSpace(): void
    {
        $response = new Response(599);

        self::assertSame(
            "HTTP/1.1 599\r\n"
            . "\r\n",
            HttpWireResponse::rendered($response, 64),
        );
    }

    public function testABodyBeyondTheBoundIsTruncatedInsideTheResponseBlock(): void
    {
        $response = new Response(200, [], '0123456789A');

        self::assertSame(
            "HTTP/1.1 200 OK\r\n"
            . "\r\n"
            . "01234567\n[3 of 11 bytes truncated]\n",
            HttpWireResponse::rendered($response, 8),
        );
    }
}
