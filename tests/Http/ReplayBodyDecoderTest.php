<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use JsonException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\FormBodyParser;
use Zef\Framework\Http\ReplayBodyDecoder;
use Zef\Framework\Http\ReplayBodyLane;
use Zef\Test\Support\Http\ReplaySnapshotDoubleState;
use Zef\Test\Support\Http\ReplaySnapshotFunctionDoubles;

/**
 * Pins the decoder's own media-type vocabulary and JSON depth bound at the
 * unit seam, where the match arms -- not the capturing boundary's catch --
 * decide the answers: the store's battery already pins the composed
 * behaviour (redaction, replayability flags), while this battery pins the
 * branch table itself, including the default arm's refusal and the encode
 * contract the re-serialised body carries.
 *
 * @internal
 */
#[CoversClass(ReplayBodyDecoder::class)]
#[CoversClass(ReplayBodyLane::class)]
final class ReplayBodyDecoderTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        ReplaySnapshotFunctionDoubles::define();
    }

    #[Override]
    protected function tearDown(): void
    {
        ReplaySnapshotFunctionDoubles::reset();
    }

    /**
     * @param non-empty-string $mediaType the media type outside the structured vocabulary
     */
    #[DataProvider('provideMediaTypesOutsideTheStructuredVocabularyAnswerNothingReplayableCases')]
    public function testMediaTypesOutsideTheStructuredVocabularyAnswerNothingReplayable(
        string $mediaType,
    ): void {
        self::assertSame(['', false], ReplayBodyDecoder::decode($mediaType, '{"token":"secret123"}'));
    }

    /**
     * Every media type below misses the vocabulary for a different reason:
     * plain text, a structured suffix on a non-application tree, and the
     * deceptive json substring the store's battery already pins end to end.
     *
     * @return iterable<non-empty-string, array{string}>
     */
    public static function provideMediaTypesOutsideTheStructuredVocabularyAnswerNothingReplayableCases(): iterable
    {
        yield 'plain text' => ['text/plain'];

        yield 'structured suffix on a foreign tree' => ['text/vnd.custom+json'];

        yield 'deceptive json substring' => ['application/json-evil'];
    }

    public function testAnOverBoundFormCaptureAnswersNotReplayableRatherThanRefusing(): void
    {
        // Issue #801's finding 3, the replay twin: a captured
        // ampersand storm is censused through the same bound the
        // url-encoded strategy holds, but the failure lane this family
        // exists to serve never throws over a body it merely records
        // -- the over-bound capture answers not-replayable instead.
        $storm = 'a=1' . str_repeat('&x=1', FormBodyParser::PAIR_LIMIT);

        self::assertSame(
            ['', false],
            ReplayBodyDecoder::decode('application/x-www-form-urlencoded', $storm),
        );
    }

    public function testAFormCaptureExactlyAtThePairBoundStillReplays(): void
    {
        // The bound's own edge, the replay twin: a capture of exactly the
        // thousand pairs the posture permits still replays -- the
        // not-replayable answer starts one pair beyond the line itself.
        $atBound = 'a=1' . str_repeat('&x=1', FormBodyParser::PAIR_LIMIT - 1);

        [$sanitized, $replayable] = ReplayBodyDecoder::decode('application/x-www-form-urlencoded', $atBound);

        self::assertTrue($replayable);
        self::assertNotSame('', $sanitized);
    }

    public function testJsonBodiesAtTheDepthBoundDecodeReplayably(): void
    {
        $body = str_repeat('{"a":', 31) . '1' . str_repeat('}', 31);

        [$sanitized, $replayable] = ReplayBodyDecoder::decode('application/json', $body);

        self::assertTrue($replayable);
        self::assertNotSame('', $sanitized);
    }

    public function testJsonBodiesBeyondTheDepthBoundAreRefused(): void
    {
        $body = str_repeat('{"a":', 32) . '1' . str_repeat('}', 32);

        $this->expectException(JsonException::class);

        ReplayBodyDecoder::decode('application/json', $body);
    }

    public function testTheReSerialisationCarriesTheFailClosedAndSubstitutingEncodeFlags(): void
    {
        ReplayBodyDecoder::decode('application/json', '{"token":"secret123"}');

        self::assertSame(
            [\JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE],
            array_map(
                static fn (array $call): int => $call[0],
                ReplaySnapshotDoubleState::$jsonEncodeCalls,
            ),
            'The replay body must be re-serialised fail-closed with invalid-UTF-8 substitution.',
        );
    }
}
