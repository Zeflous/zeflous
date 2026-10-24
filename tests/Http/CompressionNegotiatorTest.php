<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\CompressionCapabilities;
use Zef\Framework\Http\CompressionNegotiator;
use Zef\Framework\Http\ContentCoding;
use Zef\Framework\Http\HttpException;

/**
 * The negotiation, on its own terms: the preference order the
 * roadmap spells, the client's grant, the runtime's capability, and
 * every honest refusal in between.
 *
 * @internal
 */
#[CoversClass(CompressionNegotiator::class)]
#[CoversClass(ContentCoding::class)]
#[CoversClass(CompressionCapabilities::class)]
final class CompressionNegotiatorTest extends TestCase
{
    public function testThePreferenceOrderWalksBestFirst(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        self::assertSame('gzip', $compressionNegotiator->negotiate('gzip, deflate'));
    }

    public function testABrotliCapableRuntimeWinsThePreference(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['br', 'gzip', 'deflate']);

        self::assertSame('br', $compressionNegotiator->negotiate('gzip, deflate, br'));
    }

    public function testABrotliClientCannotWinWithoutTheCapability(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        self::assertSame('gzip', $compressionNegotiator->negotiate('br, gzip'));
    }

    public function testAWildcardGrantsTheUnspelledPreference(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        self::assertSame('gzip', $compressionNegotiator->negotiate('deflate, *;q=0.5'));
    }

    public function testASpelledZeroRefusesEvenUnderTheWildcard(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        self::assertSame('deflate', $compressionNegotiator->negotiate('gzip;q=0, *'));
    }

    public function testTheUnspelledPreferenceWithoutAWildcardIsNotGranted(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['deflate']);

        self::assertNull(
            $compressionNegotiator->negotiate('gzip;q=0'),
            'a header that names neither the preference nor the wildcard grants nothing',
        );
    }

    public function testTheEngineProbesTheRuntimeCapability(): void
    {
        $expected = [];
        if (\function_exists('brotli_compress')) {
            $expected[] = 'br';
        }

        if (\function_exists('deflate_init')) {
            $expected[] = 'gzip';
            $expected[] = 'deflate';
        }

        self::assertSame(
            $expected,
            CompressionCapabilities::detect(),
            'the probe answers the preference order over what this build can produce',
        );
    }

    public function testAnEmptyCapabilityListAnswersIdentity(): void
    {
        $compressionNegotiator = new CompressionNegotiator([]);

        self::assertNull($compressionNegotiator->negotiate('gzip, deflate, br, *'));
    }

    public function testAnEmptyHeaderAnswersIdentity(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        self::assertNull($compressionNegotiator->negotiate(''));
    }

    public function testAClientThatRefusesEverythingSpelledAnswersIdentity(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        self::assertNull($compressionNegotiator->negotiate('gzip;q=0, deflate;q=0, br;q=0, *;q=0'));
    }

    public function testAPreferenceTheRuntimeCannotProduceIsNeverNegotiated(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['deflate']);

        self::assertSame('deflate', $compressionNegotiator->negotiate('gzip, deflate'));
    }

    public function testThePreferenceOrderIsTheCapabilityOrderFiltered(): void
    {
        self::assertSame(['gzip', 'deflate'], ContentCoding::preferences(['deflate', 'gzip', 'x-custom']));
        self::assertSame(
            ['br', 'gzip', 'deflate'],
            ContentCoding::preferences(['gzip', 'br', 'deflate']),
            'the preference order reorders whatever the capability list spells',
        );
        self::assertSame([], ContentCoding::preferences(['x-custom', 'zstd']));
    }

    public function testAVocabularyWindowIsSpelledForEachCoding(): void
    {
        self::assertSame(31, ContentCoding::window('gzip'), 'the gzip wrapper asks for window 15 with the gzip bit');
        self::assertSame(15, ContentCoding::window('deflate'), 'HTTP deflate is the zlib wrapper, window 15 bare');
    }

    public function testACodingWithoutAWindowIsRefused(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains("The content coding 'identity' has no zlib window.");

        ContentCoding::window('identity');
    }
}
