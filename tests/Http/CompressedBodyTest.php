<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\CompressedBody;
use Zef\Framework\Http\Stream;
use Zef\Test\Support\Http\LyingEofStreamWrapper;

/**
 * The body fold, on its own terms: the round-trip through each
 * coding's own decoder, the memory-safe destination, the source
 * rewind, and the level the engine is asked for.
 *
 * @internal
 */
#[CoversClass(CompressedBody::class)]
final class CompressedBodyTest extends TestCase
{
    private const string PAYLOAD = "The compression family answers the roadmap's Phase 3.2 engine. ";

    private const string LYING_EOF_PROTOCOL = 'zef-lying-eof';

    #[Override]
    protected function setUp(): void
    {
        stream_wrapper_register(self::LYING_EOF_PROTOCOL, LyingEofStreamWrapper::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        stream_wrapper_unregister(self::LYING_EOF_PROTOCOL);
    }

    public function testTheGzipCodingRoundTrips(): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);

        $compressed = CompressedBody::compress(Stream::fromString($payload), 'gzip', 6);

        self::assertSame(
            $payload,
            gzdecode((string) $compressed),
            "the gzip wrapper's own decoder answers the original bytes",
        );
    }

    public function testTheDeflateCodingRoundTrips(): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);

        $compressed = CompressedBody::compress(Stream::fromString($payload), 'deflate', 6);

        self::assertSame(
            $payload,
            gzuncompress((string) $compressed),
            'HTTP deflate is the zlib wrapper, and its own decoder answers the original bytes',
        );
    }

    public function testACompressibleBodyShrinks(): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);

        $compressed = CompressedBody::compress(Stream::fromString($payload), 'gzip', 6);

        self::assertLessThan(
            mb_strlen($payload),
            $compressed->getSize(),
            'a repetitive payload compresses to a fraction of itself',
        );
    }

    public function testTheDestinationIsSeekableAndSized(): void
    {
        $compressed = CompressedBody::compress(
            Stream::fromString(str_repeat('a', 4096)),
            'gzip',
            6,
        );

        self::assertTrue($compressed->isSeekable());
        self::assertGreaterThan(0, $compressed->getSize());
        self::assertSame(0, $compressed->tell(), 'the fold hands back a rewound stream');
    }

    public function testASourcePastItsEndIsRewoundFirst(): void
    {
        $source = Stream::fromString('compressible payload, compressible payload');
        $consumed = $source->read(8192);
        self::assertSame('compressible payload, compressible payload', $consumed);

        $compressed = CompressedBody::compress($source, 'gzip', 6);

        self::assertSame(
            'compressible payload, compressible payload',
            gzdecode((string) $compressed),
            'the fold rewinds a seekable source it did not create',
        );
    }

    public function testALargeBodySurvivesTheMemoryAllowance(): void
    {
        $payload = str_repeat(self::PAYLOAD, 2048);

        $compressed = CompressedBody::compress(Stream::fromString($payload), 'gzip', 6);

        self::assertSame(
            $payload,
            gzdecode((string) $compressed),
            "a body past the destination's 64 KB in-memory allowance spills to disk and still decodes",
        );
    }

    public function testTheLevelReadsThroughToTheEngine(): void
    {
        $payload = '';
        foreach (range(0, 2047) as $i) {
            $payload .= hash('sha256', (string) $i);
        }

        $fast = CompressedBody::compress(Stream::fromString($payload), 'gzip', 1);
        $best = CompressedBody::compress(Stream::fromString($payload), 'gzip', 9);

        self::assertSame($payload, gzdecode((string) $fast));
        self::assertSame($payload, gzdecode((string) $best));
        self::assertLessThan(
            $fast->getSize(),
            $best->getSize(),
            'a harder level buys real bytes on a weakly-compressible payload',
        );
        self::assertGreaterThan(0, $fast->getSize());
    }

    public function testAnEmptyBodyCompressesToItsOwnFraming(): void
    {
        $compressed = CompressedBody::compress(Stream::fromString(''), 'gzip', 6);

        self::assertSame('', gzdecode((string) $compressed));
        self::assertGreaterThan(0, $compressed->getSize(), 'the gzip wrapper alone carries bytes');
    }

    public function testTheWalkEndsWhenAChunkReadsEmptyBelowEof(): void
    {
        $stream = new Stream(fopen(self::LYING_EOF_PROTOCOL . '://payload', 'rb'));

        $compressed = CompressedBody::compress($stream, 'gzip', 6);

        self::assertSame(
            'AB',
            gzdecode((string) $compressed),
            'the empty read below a lying not-at-eof is the defensive stop the walk guards',
        );
    }
}
