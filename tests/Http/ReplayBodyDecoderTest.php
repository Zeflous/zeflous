<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use JsonException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ReplayBodyDecoder;
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
