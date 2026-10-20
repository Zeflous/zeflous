<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ReplayBodyCapture;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;

/**
 * Pins the capturing boundary's own content-type reading: the media type
 * is the first semicolon-delimited segment of the folded header, so the
 * charset parameter the wire carries constantly must not hide a structured
 * media type from the decoder. The store's battery composes this path end
 * to end with bare media types; this battery isolates the parameter split.
 *
 * @internal
 */
#[CoversClass(ReplayBodyCapture::class)]
final class ReplayBodyCaptureTest extends TestCase
{
    /**
     * @param non-empty-string $contentType the header line whose parameter tail must be peeled
     */
    #[DataProvider('provideParameterisedStructuredContentTypesStayReplayableCases')]
    public function testParameterisedStructuredContentTypesStayReplayable(string $contentType): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.test/users'),
            [],
            [],
            [],
            [],
            null,
            ['Content-Type' => $contentType],
            Stream::fromString('{"token":"secret123"}'),
        );

        self::assertSame(['{"token":"[REDACTED]"}', true], ReplayBodyCapture::capture($serverRequest));
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function provideParameterisedStructuredContentTypesStayReplayableCases(): iterable
    {
        yield 'json with a charset parameter' => ['application/json; charset=utf-8'];

        yield 'structured suffix with a charset parameter' => ['application/vnd.api+json; charset=utf-8'];
    }
}
