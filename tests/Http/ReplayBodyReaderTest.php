<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Zef\Framework\Http\ReplayBodyReader;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\LyingSizeStream;
use Zef\Test\Support\Http\NonSeekableBody;

/**
 * Pins the body reader's own guard table at the unit seam: the empty-body
 * early answer, the unknown-size refusal, and the inclusive byte ceiling --
 * every boundary the store's battery composes but never isolates, exercised
 * through the deterministic stream doubles the ingress gate's own battery
 * introduced (the lying-size and non-seekable shapes).
 *
 * @internal
 */
#[CoversClass(ReplayBodyReader::class)]
final class ReplayBodyReaderTest extends TestCase
{
    public function testAnEmptyBodyAnswersTheEmptyStringEvenWhenTheStreamCannotSeek(): void
    {
        $serverRequest = $this->requestWithBody(new LyingSizeStream(new NonSeekableBody(''), 0));

        self::assertSame('', ReplayBodyReader::read($serverRequest));
    }

    public function testAnUnknownSizeIsRefusedEvenWhenTheStreamCanSeek(): void
    {
        $serverRequest = $this->requestWithBody(new LyingSizeStream(Stream::fromString('{"token":"x"}'), null));

        self::assertNull(ReplayBodyReader::read($serverRequest));
    }

    public function testTheByteCeilingIsInclusiveAtTheBound(): void
    {
        $body = '{"payload":"' . str_repeat('a', 65_522) . '"}';

        self::assertSame(65_536, mb_strlen($body));
        self::assertSame($body, ReplayBodyReader::read($this->requestWithBody(Stream::fromString($body))));
    }

    public function testTheByteCeilingRefusesOneByteBeyondTheBound(): void
    {
        $body = '{"payload":"' . str_repeat('a', 65_523) . '"}';

        self::assertSame(65_537, mb_strlen($body));
        self::assertNull(ReplayBodyReader::read($this->requestWithBody(Stream::fromString($body))));
    }

    private function requestWithBody(StreamInterface $stream): ServerRequest
    {
        return new ServerRequest('POST', new Uri('https://app.example.test/users'), [], [], [], [], null, [], $stream);
    }
}
