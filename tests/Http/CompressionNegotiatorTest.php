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

    public function testASpelledZeroWithTheGrammarOwnOwsRefuses(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        self::assertNull(
            $compressionNegotiator->negotiate('gzip; q=0'),
            "the weight grammar's OWS is not an exemption from the refusal it spells "
            . '-- a client that wrote the zero through a space refused gzip all the same (round-3 audit R3-1)',
        );
    }

    public function testASpelledQualityWithTheGrammarOwnOwsReads(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        self::assertSame(
            'deflate',
            $compressionNegotiator->negotiate('gzip ; q=0, deflate'),
            "a zero spelled through the weight grammar's own OWS still refuses its coding "
            . '-- the walk falls to the next preference, exactly as the tight spelling behaves',
        );
    }

    public function testTheClientsExplicitQualityDecidesOverTheServerPreference(): void
    {
        // Issue #801's finding 5's own repro: the client spelled
        // deflate at 1.0 and gzip at 0.1 -- the deflate wins, the
        // server's gzip-first preference breaking ties alone (RFC 9110
        // section 12.5).
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        self::assertSame('deflate', $compressionNegotiator->negotiate('deflate;q=1, gzip;q=0.1'));
    }

    public function testTheServerPreferenceBreaksTheQualityTieAlone(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        self::assertSame('gzip', $compressionNegotiator->negotiate('deflate, gzip'));
    }

    public function testTheSpelledQualityOutranksTheWildcardGrant(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        // Issue #801's finding 5: the client's quality decides (RFC
        // 9110 section 12.5) -- the deflate the client spelled at 1.0
        // outranks the gzip only the wildcard grants at 0.5, the
        // server's preference never overriding an explicit quality.
        self::assertSame('deflate', $compressionNegotiator->negotiate('deflate, *;q=0.5'));
    }

    public function testASpelledZeroRefusesEvenUnderTheWildcard(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        self::assertSame('deflate', $compressionNegotiator->negotiate('gzip;q=0, *'));
    }

    public function testTheWildcardStillGrantsTheUnspelledCoding(): void
    {
        // The wildcard's own grant survives the quality-decides walk
        // (issue #801's finding 5): an unspelled producible coding the
        // wildcard grants at 0.5 outranks nothing (the client named no
        // producible coding), and the tied grants answer the server's
        // preference alone -- the wildcard's grant was never the part
        // the finding retired, only the server-first walk that ignored
        // the client's own qualities.
        $compressionNegotiator = new CompressionNegotiator(['gzip', 'deflate']);

        self::assertSame('gzip', $compressionNegotiator->negotiate('br, *;q=0.5'));
    }

    public function testTheUnspelledPreferenceWithoutAWildcardIsNotGranted(): void
    {
        $compressionNegotiator = new CompressionNegotiator(['deflate']);

        self::assertNull(
            $compressionNegotiator->negotiate('gzip;q=0'),
            'a header that names neither the preference nor the wildcard grants nothing',
        );
    }

    public function testTheEngineProbesTheFoldSVocabulary(): void
    {
        // The round-3 audit's R3-2: the probe answers the family's own
        // fold, not the function table -- a build with the brotli
        // extension must not light up a preference the fold would
        // refuse at a 500 on every compressed answer.
        $expected = [];
        if (\function_exists('deflate_init')) {
            $expected[] = 'gzip';
            $expected[] = 'deflate';
        }

        self::assertSame(
            $expected,
            CompressionCapabilities::detect(),
            "the probe answers the fold's own vocabulary over what this build can produce",
        );
    }

    public function testTheDefaultProbeNeverAnswersBrHoweverLoudlyTheClientAsks(): void
    {
        // The round-3 audit's R3-2, end to end: the default negotiator
        // reads the probe, and the probe answers the fold -- so no
        // build answers 'br' until the fold grows a brotli seam,
        // whatever the function table carries (a build with the
        // extension once answered 'br' and 500'd on the first eligible
        // body).
        $compressionNegotiator = new CompressionNegotiator();

        self::assertNotSame('br', $compressionNegotiator->negotiate('gzip, deflate, br'));
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
