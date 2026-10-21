<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\StatusFraming;

/**
 * @internal
 */
#[CoversClass(StatusFraming::class)]
final class StatusFramingTest extends TestCase
{
    /**
     * RFC 9110 §8.6: any 1xx answer forbids a Content-Length outright.
     */
    public function testAnInformationalStatusLosesTheContentLength(): void
    {
        $response = new Response(103, ['Content-Length' => '0']);

        self::assertFalse(StatusFraming::honest($response, 103)->hasHeader('Content-Length'));
    }

    public function testANoContentStatusLosesTheContentLength(): void
    {
        $response = new Response(204, ['Content-Length' => '0']);

        self::assertFalse(StatusFraming::honest($response, 204)->hasHeader('Content-Length'));
    }

    /**
     * §8.6, second paragraph: a 304 may keep a truthful Content-Length.
     */
    public function testANotModifiedStatusKeepsATruthfulContentLength(): void
    {
        $response = new Response(304, ['Content-Length' => '4'], 'body');

        self::assertSame('4', StatusFraming::honest($response, 304)->getHeaderLine('Content-Length'));
    }

    public function testAnOrdinaryStatusKeepsItsReconciledContentLength(): void
    {
        $response = new Response(200, ['Content-Length' => '4'], 'body');

        self::assertSame('4', StatusFraming::honest($response, 200)->getHeaderLine('Content-Length'));
    }

    public function testAnOrdinaryStatusLosesALyingContentLength(): void
    {
        $response = new Response(200, ['Content-Length' => '9'], 'body');

        self::assertFalse(StatusFraming::honest($response, 200)->hasHeader('Content-Length'));
    }

    public function testAMissingContentLengthStaysMissing(): void
    {
        $response = new Response(200, [], 'body');

        self::assertFalse(StatusFraming::honest($response, 200)->hasHeader('Content-Length'));
    }
}
