<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\RedactedMessage;
use Zef\Framework\Http\RedactionCandidate;

/**
 * @internal
 */
#[CoversClass(RedactedMessage::class)]
#[CoversClass(RedactionCandidate::class)]
final class RedactedMessageTest extends TestCase
{
    #[DataProvider('provideACredentialKeyMasksItsValueCases')]
    public function testACredentialKeyMasksItsValue(string $message, string $expected): void
    {
        self::assertSame($expected, RedactedMessage::of($message));
    }

    /**
     * Every vocabulary entry must mask its own value -- the membership
     * array's items are individually load-bearing.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function provideACredentialKeyMasksItsValueCases(): iterable
    {
        yield 'pass' => ['pass=abcd1234', 'pass=[REDACTED]'];

        yield 'password' => ['password=hunter2', 'password=[REDACTED]'];

        yield 'passwd with a colon separator' => ['passwd: secret99', 'passwd: [REDACTED]'];

        yield 'pwd with a whitespace separator' => ['pwd hunter2', 'pwd [REDACTED]'];

        yield 'secret' => ['secret=xyz12345', 'secret=[REDACTED]'];

        yield 'token' => ['token=abcd1234', 'token=[REDACTED]'];

        yield 'apikey' => ['apikey=deadbeef99', 'apikey=[REDACTED]'];

        yield 'api_key' => ['api_key=abcd1234', 'api_key=[REDACTED]'];

        yield 'api-key' => ['api-key=abcd1234', 'api-key=[REDACTED]'];

        yield 'authorization' => ['authorization=letmein42', 'authorization=[REDACTED]'];

        yield 'bearer as a key' => ['bearer=xyz123456', 'bearer=[REDACTED]'];

        yield 'privatekey' => ['privatekey=abcd1234', 'privatekey=[REDACTED]'];

        yield 'private_key' => ['private_key=abcd1234', 'private_key=[REDACTED]'];

        yield 'private-key' => ['private-key=abcd1234', 'private-key=[REDACTED]'];
    }

    public function testASingleTokenValuePreservesTheTrailingProse(): void
    {
        self::assertSame('password=[REDACTED] gone', RedactedMessage::of('password=hunter2 gone'));
    }

    public function testTheKeyKeepsItsOriginalCaseInTheReplacement(): void
    {
        self::assertSame('PASSWORD=[REDACTED]', RedactedMessage::of('PASSWORD=hunter2'));
    }

    /**
     * The issue #307 shape: the candidate value class stops at whitespace,
     * so every token after the scheme keyword would leak without the
     * whole-line consumption.
     */
    public function testAnUnquotedSchemeKeywordConsumesTheRestOfTheLine(): void
    {
        self::assertSame(
            'Authorization: [REDACTED]',
            RedactedMessage::of('Authorization: Bearer abc123xyz trailing'),
        );
    }

    public function testEverySchemeKeywordExtends(): void
    {
        self::assertSame('Authorization: [REDACTED]', RedactedMessage::of('Authorization: Basic dXNlcjpwYXNz'));
        self::assertSame('Authorization: [REDACTED]', RedactedMessage::of('Authorization: Digest abc123xyz'));
    }

    public function testTheSchemeKeywordJudgementIsCaseInsensitive(): void
    {
        self::assertSame('token=[REDACTED]', RedactedMessage::of('token=BEARER secret123'));
        self::assertSame('AUTHORIZATION: [REDACTED]', RedactedMessage::of('AUTHORIZATION: BEARER x1234abc'));
    }

    /**
     * The scheme extension stops at the line end, so the next line's
     * credential is still scanned and masked separately.
     */
    public function testTheSchemeExtensionStopsAtTheLineEnd(): void
    {
        self::assertSame(
            "Authorization: [REDACTED]\r\ntoken=[REDACTED]",
            RedactedMessage::of("Authorization: Bearer abc\r\ntoken=def1234"),
        );
    }

    /**
     * A quoted value already ends at its closing quote, so the scheme
     * judgement never extends it -- the trailing prose survives.
     */
    public function testAQuotedValueIsNotExtendedByASchemeKeyword(): void
    {
        self::assertSame('token="[REDACTED]" trailing', RedactedMessage::of('token="Bearer" trailing'));
        self::assertSame("api_key: '[REDACTED]'", RedactedMessage::of("api_key: 'xyz1234'"));
    }

    /**
     * The advance after a non-credential candidate resumes at the key's
     * end, which keeps a candidate overlapping the first match's span
     * scannable: "token password" is the second match (key "token", value
     * "password"). The historical behaviour's trade-off: the tail after
     * the inner match is not re-judged, so "hunter2" stays -- the exact
     * byte answer the reference produced.
     */
    public function testACandidateOverlappingASkippedSpanStaysScannable(): void
    {
        self::assertSame('duck token [REDACTED]=hunter2', RedactedMessage::of('duck token password=hunter2'));
        self::assertSame('x duck=abcd token=[REDACTED]', RedactedMessage::of('x duck=abcd token=efgh'));
    }

    /**
     * Two non-overlapping edits with replacement lengths that differ from
     * their spans: applying them right-to-left keeps both offsets honest.
     */
    public function testMultipleEditsAreAppliedRightToLeft(): void
    {
        self::assertSame(
            'password=[REDACTED] api_key=[REDACTED]',
            RedactedMessage::of('password=hunter2 api_key=abcdefgh'),
        );
    }

    public function testProseWithoutCredentialKeysPassesThrough(): void
    {
        self::assertSame('the quick brown fox', RedactedMessage::of('the quick brown fox'));
        self::assertSame('duck=abcd1234', RedactedMessage::of('duck=abcd1234'));
    }

    public function testALookalikeKeyIsNotAVocabularyHit(): void
    {
        self::assertSame('passwordless=abcd1234', RedactedMessage::of('passwordless=abcd1234'));
    }

    public function testUrlishMaterialSurvivesUntilARealCredentialAppears(): void
    {
        self::assertSame(
            'fetch https://ex.com/a/b failed for token=[REDACTED]',
            RedactedMessage::of('fetch https://ex.com/a/b failed for token=abcd1234'),
        );
    }

    public function testTheEmptyMessageAnswersItself(): void
    {
        self::assertSame('', RedactedMessage::of(''));
    }
}
