<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Telemetry\TelemetrySanitizer;

/**
 * The telemetry hygiene unit, on its own terms: the key census
 * (every fragment, the normalisation folds, the containment
 * semantics, the kelvin-sign spelling the multi-byte fold alone
 * reads), the string clamp (the control-byte strip, the
 * character-boundary truncation with its budget arithmetic, the
 * invalid-UTF-8 scrub), the pair and bearer redaction out of
 * free-form diagnostics, and the value walk (the scalar identities,
 * the NaN and infinities in their JSON-safe spellings, the
 * thirty-two-entry array budget with its nested markers).
 *
 * @internal
 */
#[CoversClass(TelemetrySanitizer::class)]
final class TelemetrySanitizerTest extends TestCase
{
    #[DataProvider('provideEveryCensusFragmentIsSensitiveCases')]
    public function testEveryCensusFragmentIsSensitive(string $key): void
    {
        self::assertTrue(TelemetrySanitizer::isSensitiveKey($key));
    }

    /**
     * Every fragment in the key census is sensitive on its own: the
     * census is the contract, one pin per entry, so a dropped entry
     * is a failure the suite names.
     *
     * @return iterable<string, list<string>>
     */
    public static function provideEveryCensusFragmentIsSensitiveCases(): iterable
    {
        yield 'authorization' => ['authorization'];

        yield 'cookie' => ['cookie'];

        yield 'set-cookie' => ['set-cookie'];

        yield 'password' => ['password'];

        yield 'passwd' => ['passwd'];

        yield 'token' => ['token'];

        yield 'secret' => ['secret'];

        yield 'api-key' => ['api-key'];

        yield 'apikey' => ['apikey'];

        yield 'access-token' => ['access-token'];

        yield 'refresh-token' => ['refresh-token'];

        yield 'client-secret' => ['client-secret'];
    }

    #[DataProvider('provideTheCensusNormalisesBeforeItJudgesCases')]
    public function testTheCensusNormalisesBeforeItJudges(string $key): void
    {
        self::assertTrue(TelemetrySanitizer::isSensitiveKey($key));
    }

    /**
     * The normalisation folds: underscores and spaces fold to the
     * hyphen before the comparison, the case fold is the multi-byte
     * one, and the containment semantics accept the fragment inside
     * a longer key -- a `db_password` and a `x-password-y` are the
     * same refusal the bare `password` is.
     *
     * @return iterable<string, list<string>>
     */
    public static function provideTheCensusNormalisesBeforeItJudgesCases(): iterable
    {
        yield 'underscore folds to the hyphen' => ['db_password'];

        yield 'underscore fragment folds' => ['api_key'];

        yield 'space folds to the hyphen' => ['api key'];

        yield 'the case fold is case-insensitive' => ['Password'];

        yield 'mixed case folds' => ['API_KEY'];

        yield 'containment, leading' => ['password_hint'];

        yield 'containment, trailing' => ['user_password'];

        yield 'containment, both sides' => ['x-password-y'];

        yield 'access token, spaced' => ['Access Token'];
    }

    /**
     * The kelvin sign (U+212A) folds onto the ASCII `k` through the
     * multi-byte case map alone: the byte-level fold leaves it
     * untouched, so this spelling's fragment is one only the
     * mandated fold can read -- the same pin the correlation
     * redaction's census carries.
     */
    public function testTheKelvinSignFoldReadsTheFragment(): void
    {
        $key = "to\u{212A}en";

        self::assertTrue(TelemetrySanitizer::isSensitiveKey($key), 'the kelvin sign folds onto the ASCII k');
    }

    /**
     * The pair vocabulary's own fold carries the same kelvin-sign
     * pin the key census does: a pair whose key word spells its
     * `apikey` fragment through the case map alone is redacted by
     * the multi-byte fold and rides unredacted through the
     * byte-level one.
     */
    public function testTheKelvinSignFoldReadsThePairVocabulary(): void
    {
        $key = "API\u{212A}EY";

        self::assertSame(
            $key . '=[REDACTED]',
            TelemetrySanitizer::redact($key . '=secret1'),
            'the kelvin sign folds onto the ASCII k the vocabulary ends with',
        );
    }

    #[DataProvider('provideTheHonestVocabularyIsNotSensitiveCases')]
    public function testTheHonestVocabularyIsNotSensitive(string $key): void
    {
        self::assertFalse(TelemetrySanitizer::isSensitiveKey($key));
    }

    /**
     * The honest telemetry vocabulary is not sensitive: the request
     * semantics the families record stay recordable, and a key that
     * merely rhymes with a fragment is not a refusal.
     *
     * @return iterable<string, list<string>>
     */
    public static function provideTheHonestVocabularyIsNotSensitiveCases(): iterable
    {
        yield 'the method' => ['http.method'];

        yield 'the path' => ['http.path'];

        yield 'the host' => ['http.host'];

        yield 'the status code' => ['http.status_code'];

        yield 'the attempt count' => ['attempts'];

        yield 'the exception type' => ['exception.type'];

        yield 'the response size' => ['response_bytes'];
    }

    public function testTheControlBytesAreStrippedAndTheWhitespaceStays(): void
    {
        self::assertSame(
            'abc d',
            TelemetrySanitizer::string("a\x01b\x7Fc d"),
            'the control bytes below tab, the VT/FF band and DEL strip; the space stays',
        );
    }

    public function testTheClampKeepsAStringAtItsBudget(): void
    {
        $exact = str_repeat('a', 2048);

        self::assertSame($exact, TelemetrySanitizer::string($exact), 'a string at the budget answers itself');
    }

    public function testTheClampTruncatesOverBudgetBytesWithTheMarker(): void
    {
        $over = str_repeat('a', 2049);

        self::assertSame(
            str_repeat('a', 2045) . '…',
            TelemetrySanitizer::string($over),
            '2049 bytes answer 2045 plus the three-byte ellipsis: exactly the budget',
        );
    }

    public function testTheClampCutsAtTheCharacterBoundary(): void
    {
        // 2044 ASCII bytes then three two-byte characters: the byte
        // budget's cut would land inside the first one, the
        // character-boundary cut drops it whole and marks.
        $value = str_repeat('a', 2044) . str_repeat("\u{00E9}", 3);

        self::assertSame(
            str_repeat('a', 2044) . '…',
            TelemetrySanitizer::string($value),
            'the cut lands on a boundary and the marker closes under the budget',
        );
    }

    public function testTheClampTruncatesMultibytePayloadsByBytes(): void
    {
        // Two-byte characters, six of them: the byte budget (10) cuts
        // whole characters until the marker closes it.
        $value = str_repeat("\u{00E9}", 8);

        self::assertSame(
            str_repeat("\u{00E9}", 3) . '…',
            TelemetrySanitizer::string($value, 10),
        );
    }

    public function testAUnitBudgetCutsOneCharacter(): void
    {
        self::assertSame(
            'a',
            TelemetrySanitizer::string('abc', 1),
            'a budget of one is a budget -- the narrowest clamp the grammar accepts cuts one character',
        );
    }

    public function testANarrowBudgetUnderTheMarkerTruncatesBare(): void
    {
        // A budget of three cannot carry the three-byte marker and
        // any payload: the cut is bare, at the character boundary.
        self::assertSame('ab', TelemetrySanitizer::string('abé', 3));
        self::assertSame('abc', TelemetrySanitizer::string('abcd', 3));
        self::assertSame("\u{00E9}", TelemetrySanitizer::string("\u{00E9}x", 2));
    }

    public function testABudgetBelowOneAnswersTheEmptyString(): void
    {
        self::assertSame('', TelemetrySanitizer::string('anything', 0));
        self::assertSame('', TelemetrySanitizer::string('anything', -1));
    }

    public function testInvalidUtf8IsScrubbedBeforeItClamps(): void
    {
        // "\xE9" alone is an invalid UTF-8 sequence: the scrub answers
        // the configured substitution character (the engine default,
        // a question mark in the shipped ini), so a downstream JSON
        // encode never throws on it. The observables are the ones
        // every environment agrees on: the answer is valid UTF-8, and
        // the invalid byte is gone.
        $scrubbed = TelemetrySanitizer::string("a\xE9");

        self::assertTrue(mb_check_encoding($scrubbed, 'UTF-8'), 'the scrub answers valid UTF-8');
        self::assertSame('a', mb_substr($scrubbed, 0, 1, 'UTF-8'));
        self::assertSame(2, mb_strlen($scrubbed, 'UTF-8'), 'one substitution answers the invalid byte');
    }

    #[DataProvider('provideThePairVocabularyRedactsItsSuffixesCases')]
    public function testThePairVocabularyRedactsItsSuffixes(string $given, string $expected): void
    {
        self::assertSame($expected, TelemetrySanitizer::redact($given));
    }

    /**
     * The pair redaction's vocabulary: every suffix in the census
     * redacts the word it ends, in the spelling it ends it -- the
     * census is the contract, one pin per family of spellings.
     *
     * @return iterable<string, list<string>>
     */
    public static function provideThePairVocabularyRedactsItsSuffixesCases(): iterable
    {
        yield 'bare password' => ['password=hunter2', 'password=[REDACTED]'];

        yield 'colon separator' => ['password: hunter2', 'password=[REDACTED]'];

        yield 'json-quoted pair' => ['"password":"hunter2"', 'password=[REDACTED]'];

        yield 'single-quoted value' => ["password='hunter2'", 'password=[REDACTED]'];

        yield 'prefixed key word' => ['db_password=hunter2', 'db_password=[REDACTED]'];

        yield 'underscore spelling' => ['api_key=abc123', 'api_key=[REDACTED]'];

        yield 'hyphen spelling' => ['api-key=abc123', 'api-key=[REDACTED]'];

        yield 'access token' => ['access_token=abc', 'access_token=[REDACTED]'];

        yield 'client secret' => ['client-secret=abc', 'client-secret=[REDACTED]'];

        yield 'private key' => ['private_key=abc', 'private_key=[REDACTED]'];

        yield 'credentials' => ['credentials=abc', 'credentials=[REDACTED]'];

        yield 'uppercase key folds' => ['PASSWORD=hunter2', 'PASSWORD=[REDACTED]'];

        yield 'the pair inside a sentence' => [
            'login for user=a with token=xyz failed',
            'login for user=a with token=[REDACTED] failed',
        ];
    }

    public function testAHonestPairStays(): void
    {
        self::assertSame(
            'user=anwar attempts=3',
            TelemetrySanitizer::redact('user=anwar attempts=3'),
        );
    }

    /**
     * The bearer redaction: the RFC 6750 token68 shape after the
     * scheme word, either case, padding included -- the credential
     * material never rides a diagnostic out of the process.
     */
    public function testTheBearerTokenIsRedacted(): void
    {
        self::assertSame(
            'Authorization was Bearer [REDACTED]',
            TelemetrySanitizer::redact('Authorization was Bearer abcDEF123._~+/=='),
        );
        self::assertSame(
            'Bearer [REDACTED] accepted',
            TelemetrySanitizer::redact('bearer abc123 accepted'),
            'the replacement carries its own spelling -- the scheme word the pattern matched',
        );
    }

    public function testTheRedactionClampsLikeEveryString(): void
    {
        $long = 'password=' . str_repeat('x', 3000);

        self::assertSame(
            'password=[REDACTED]',
            TelemetrySanitizer::redact($long),
            'the pair is gone before the clamp ever runs',
        );
    }

    public function testTheRedactionsOwnDefaultBudgetClamps(): void
    {
        // A pair-free diagnostic over the redaction's own default
        // budget: the clamp the default declares is the 2048 every
        // other clamp answers through, marked at the cut.
        self::assertSame(
            str_repeat('x', 2045) . '…',
            TelemetrySanitizer::redact(str_repeat('x', 3000)),
            'the redaction clamps at its own default budget when no explicit one was given',
        );
    }

    public function testScalarValuesAnswerTheirIdentities(): void
    {
        self::assertSame('plain', TelemetrySanitizer::value('plain'));
        self::assertSame(7, TelemetrySanitizer::value(7));
        self::assertTrue(TelemetrySanitizer::value(true));
        self::assertNull(TelemetrySanitizer::value(null));
        self::assertSame(1.5, TelemetrySanitizer::value(1.5));
    }

    /**
     * The NaN and infinities answer their string spellings: the JSON
     * grammar refuses the raw floats, and a diagnostic that carried
     * one would take the whole delivery batch down with it.
     */
    public function testTheUnencodableFloatsAnswerTheirSpellings(): void
    {
        self::assertSame('NAN', TelemetrySanitizer::value(\NAN));
        self::assertSame('INF', TelemetrySanitizer::value(\INF));
        self::assertSame('-INF', TelemetrySanitizer::value(-\INF));
    }

    public function testAnOverBudgetStringValueClamps(): void
    {
        self::assertSame(
            str_repeat('a', 2045) . '…',
            TelemetrySanitizer::value(str_repeat('a', 3000)),
        );
    }

    public function testAnObjectAnswersItsDebugType(): void
    {
        self::assertSame(stdClass::class, TelemetrySanitizer::value(new stdClass()));
    }

    public function testAnArrayValueWalksUnderItsEntryBudget(): void
    {
        $thirtyTwo = array_fill(0, 32, 'entry');

        self::assertSame(
            $thirtyTwo,
            TelemetrySanitizer::value($thirtyTwo),
            'thirty-two entries all answer',
        );

        $thirtyThree = array_fill(0, 33, 'entry');
        $sanitized = TelemetrySanitizer::value($thirtyThree);

        self::assertIsArray($sanitized);
        self::assertCount(32, $sanitized, 'the thirty-third entry drops');
    }

    public function testASensitiveKeyInsideAnArrayAnswersTheMarker(): void
    {
        self::assertSame(
            ['api_token' => '[REDACTED]', 'honest' => 'value'],
            TelemetrySanitizer::value(['api_token' => 'hunter2', 'honest' => 'value']),
            'the walk saw an honest outer key and a sensitive entry key: the marker answers',
        );
    }

    public function testTheAttributeMapDropsSensitiveKeysAndClampsTheRest(): void
    {
        self::assertSame(
            [
                'http.method' => 'GET',
                'attempts' => 2,
                'note' => str_repeat('a', 2045) . '…',
            ],
            TelemetrySanitizer::attributes([
                'http.method' => 'GET',
                'attempts' => 2,
                'note' => str_repeat('a', 3000),
                'api_token' => 'material that must never exist in the collection',
            ]),
        );
    }

    public function testAValueWalkBeyondTheDepthBoundAnswersTheMarker(): void
    {
        // The logging sibling's own cap, mirrored: a deeply-nested or
        // self-referential attribute array answers the truncation
        // marker at the bound instead of exhausting the stack inside
        // the request path.
        $nested = ['leaf'];
        for ($i = 0; $i < 64; ++$i) {
            $nested = ['level' => $nested];
        }

        $sanitized = TelemetrySanitizer::value($nested);

        $walk = $sanitized;
        $depth = 0;

        while (\is_array($walk) && !\array_key_exists('_truncated', $walk)) {
            $walk = $walk['level'] ?? null;
            ++$depth;
        }

        self::assertLessThanOrEqual(5, $depth, 'the walk stopped at the bound');
        self::assertSame(['_truncated' => true], $walk, 'the bound answers the marker');
    }

    public function testASelfReferentialArraySurvivesTheWalk(): void
    {
        $circular = [];
        $circular['self'] = &$circular;

        $sanitized = TelemetrySanitizer::value($circular);
        unset($circular);

        // The walk terminates (the bound answered) and the marker sits
        // at the bottom of the sanitised copy -- the reference itself
        // never rides the sanitised answer.
        $walk = $sanitized;
        $depth = 0;

        while (\is_array($walk) && \array_key_exists('self', $walk)) {
            $walk = $walk['self'];
            ++$depth;
        }

        self::assertLessThanOrEqual(5, $depth, 'the self-reference walked no deeper than the bound');
        self::assertSame(['_truncated' => true], $walk);
    }

    public function testARefusedLeadingKeyDoesNotStopTheWalk(): void
    {
        self::assertSame(
            ['honest' => 'value'],
            TelemetrySanitizer::attributes([
                'api_token' => 'material that must never exist in the collection',
                'honest' => 'value',
            ]),
            'a refusal skips the entry -- the walk carries on to the honest keys behind it',
        );
    }
}
