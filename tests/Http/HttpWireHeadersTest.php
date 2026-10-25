<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpWireHeaders;
use Zef\Framework\Http\Response;

/**
 * The wire-dump family's header fold: one line per field VALUE in the
 * message's own declaration order -- a repeated field answers one
 * line per value, never the joined spelling -- and a message without
 * header fields answers no lines at all.
 *
 * @internal
 */
#[CoversClass(HttpWireHeaders::class)]
final class HttpWireHeadersTest extends TestCase
{
    public function testEachFieldAnswersOneLineInDeclarationOrder(): void
    {
        $response = new Response(200, [
            'Content-Type' => 'text/plain',
            'X-First' => 'one',
            'X-Second' => 'two',
        ]);

        self::assertSame(
            ['Content-Type: text/plain', 'X-First: one', 'X-Second: two'],
            HttpWireHeaders::lines($response),
        );
    }

    public function testARepeatedFieldAnswersOneLinePerValue(): void
    {
        $response = new Response(200, [
            'Accept' => ['application/json', 'text/html'],
            'Vary' => 'Accept',
        ]);

        self::assertSame(
            ['Accept: application/json', 'Accept: text/html', 'Vary: Accept'],
            HttpWireHeaders::lines($response),
        );
    }

    public function testAMessageWithoutHeaderFieldsAnswersNoLines(): void
    {
        self::assertSame([], HttpWireHeaders::lines(new Response(200)));
    }
}
