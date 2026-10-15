<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\AuthBounds;
use Zef\Framework\Http\AuthFieldKnobs;
use Zef\Framework\Http\AuthKnobs;
use Zef\Framework\Http\AuthPolicy;
use Zef\Framework\Http\AuthTokenKnobs;
use Zef\Framework\Http\HttpException;

/**
 * The auth family's knob reading, on its own terms: the default
 * posture, the full knob map, the environment's string spellings, the
 * token table's two spellings and its shape bounds, the principal
 * fields' bounds, the replay pair's range, and the unknown-key census
 * -- plus the profile mirror the CSRF family pinned the same way.
 *
 * @internal
 */
#[CoversClass(AuthPolicy::class)]
#[CoversClass(AuthKnobs::class)]
#[CoversClass(AuthTokenKnobs::class)]
#[CoversClass(AuthFieldKnobs::class)]
#[CoversClass(AuthBounds::class)]
#[CoversClass(FrameworkDefaults::class)]
final class AuthPolicyTest extends TestCase
{
    private const string TOKEN = 'dGFibGUtdG9rZW4tZnJvbS10aGUtdGVzdC1zdWl0ZQ';

    private const string SECOND_TOKEN = 'c2Vjb25kLXRhYmxlLXRva2VuLWZyb20tdGVzdA';

    public function testAnAbsentFamilyReadsTheDefaultPosture(): void
    {
        $authPolicy = AuthPolicy::fromMap(null);

        self::assertTrue($authPolicy->enabled);
        self::assertSame([], $authPolicy->tokens);
        self::assertSame('api', $authPolicy->principal);
        self::assertSame('api', $authPolicy->scope);
        self::assertSame('api', $authPolicy->requiredScope);
        self::assertFalse($authPolicy->allowAnonymous);
        self::assertSame(1024, $authPolicy->replayCapacity);
        self::assertSame(300, $authPolicy->replayWindow);
    }

    public function testTheFrameworkProfileMirrorsThePolicyDefaults(): void
    {
        $fromEmptyMap = AuthPolicy::fromMap([]);
        $security = FrameworkDefaults::profile()['security'] ?? null;
        self::assertIsArray($security);
        $fromDefaults = $security['auth'] ?? null;
        self::assertIsArray($fromDefaults);

        self::assertSame(
            [
                'enabled' => $fromEmptyMap->enabled,
                'tokens' => $fromEmptyMap->tokens,
                'principal' => $fromEmptyMap->principal,
                'scope' => $fromEmptyMap->scope,
                'required_scope' => $fromEmptyMap->requiredScope,
                'allow_anonymous' => $fromEmptyMap->allowAnonymous,
                'replay_capacity' => $fromEmptyMap->replayCapacity,
                'replay_window' => $fromEmptyMap->replayWindow,
            ],
            $fromDefaults,
        );
    }

    public function testAReplayPairOfExactlyOneAdmits(): void
    {
        $authPolicy = new AuthPolicy(tokens: [self::TOKEN], replayCapacity: 1, replayWindow: 1);

        self::assertSame(
            1,
            $authPolicy->replayCapacity,
            'a capacity of exactly one is the floor the protector itself holds',
        );
        self::assertSame(
            1,
            $authPolicy->replayWindow,
            'a window of exactly one millisecond is the floor the protector itself holds',
        );
    }

    public function testTheTokenTableKeepsEveryEntry(): void
    {
        $authPolicy = AuthPolicy::fromMap(['tokens' => [self::TOKEN, self::SECOND_TOKEN]]);

        self::assertSame(
            [self::TOKEN, self::SECOND_TOKEN],
            $authPolicy->tokens,
            'the table carries every entry in its own order, so a client can present any of them',
        );
    }

    public function testAFieldOfExactlyTheBoundCarries(): void
    {
        $authPolicy = AuthPolicy::fromMap(['principal' => str_repeat('p', 128)]);

        self::assertSame(str_repeat('p', 128), $authPolicy->principal);
    }

    public function testAMultiByteFieldIsMeasuredInCharacters(): void
    {
        $authPolicy = AuthPolicy::fromMap(['principal' => str_repeat('é', 65)]);

        self::assertSame(
            str_repeat('é', 65),
            $authPolicy->principal,
            '65 characters are 130 bytes, and the field bound is 128 characters',
        );
    }

    public function testAnUnknownKnobAfterAKnownOneStillHalts(): void
    {
        try {
            AuthPolicy::fromMap(['enabled' => true, 'bogus' => 1]);
            self::fail('The census must keep walking past a known knob to reject the unknown one behind it.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security.auth configuration carries the unknown knob 'bogus'"
                . ' (known knobs: enabled, tokens, principal, scope, required_scope,'
                . ' allow_anonymous, replay_capacity, replay_window).',
                $httpException->getMessage(),
            );
        }
    }

    public function testAMultiByteTokenBelowTheFloorSpellsTheWholeFloorMessage(): void
    {
        try {
            AuthPolicy::fromMap(['tokens' => [str_repeat('é', 16)]]);
            self::fail('A token below the floor must be refused.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth tokens knob carries a token below the 32-character floor:'
                . ' static bearer tokens are brute-forceable below it.',
                $httpException->getMessage(),
                '16 characters are 32 bytes: the floor counts characters, so the byte spelling must not pass it',
            );
        }
    }

    public function testAMultiByteTokenAboveTheCeilingInBytesStillFailsTheGrammar(): void
    {
        try {
            AuthPolicy::fromMap(['tokens' => [str_repeat('é', 257)]]);
            self::fail('A token outside the token68 grammar must be refused.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth tokens knob carries a token that is not a bearer credential'
                . ' (RFC 6750 token68: letters, digits, - . _ ~ + /, with = padding at the end only).',
                $httpException->getMessage(),
                '257 characters are 514 bytes: the ceiling counts'
                    . 'characters, so the byte spelling must not trip it early',
            );
        }
    }

    public function testAMalformedTokenSpellsTheWholeGrammarMessage(): void
    {
        try {
            AuthPolicy::fromMap(['tokens' => [str_repeat('a', 31) . '!']]);
            self::fail('A token outside the token68 grammar must be refused.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth tokens knob carries a token that is not a bearer credential'
                . ' (RFC 6750 token68: letters, digits, - . _ ~ + /, with = padding at the end only).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFullKnobMapReads(): void
    {
        $authPolicy = AuthPolicy::fromMap([
            'enabled' => false,
            'tokens' => [self::TOKEN],
            'principal' => 'service',
            'scope' => 'api:read',
            'required_scope' => 'api:read',
            'allow_anonymous' => true,
            'replay_capacity' => 2048,
            'replay_window' => 600,
        ]);

        self::assertFalse($authPolicy->enabled);
        self::assertSame([self::TOKEN], $authPolicy->tokens);
        self::assertSame('service', $authPolicy->principal);
        self::assertSame('api:read', $authPolicy->scope);
        self::assertSame('api:read', $authPolicy->requiredScope);
        self::assertTrue($authPolicy->allowAnonymous);
        self::assertSame(2048, $authPolicy->replayCapacity);
        self::assertSame(600, $authPolicy->replayWindow);
    }

    public function testTheEnvironmentSpellingsRead(): void
    {
        $authPolicy = AuthPolicy::fromMap([
            'enabled' => 'on',
            'tokens' => self::TOKEN . ' , ' . self::SECOND_TOKEN . ',',
            'allow_anonymous' => '1',
            'replay_capacity' => '2048',
            'replay_window' => '600',
        ]);

        self::assertTrue($authPolicy->enabled);
        self::assertSame([self::TOKEN, self::SECOND_TOKEN], $authPolicy->tokens);
        self::assertTrue($authPolicy->allowAnonymous);
        self::assertSame(2048, $authPolicy->replayCapacity);
        self::assertSame(600, $authPolicy->replayWindow);
    }

    public function testANonMapSubtreeHalts(): void
    {
        try {
            AuthPolicy::fromMap('auth');
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobHaltsWithTheKnownListSpelledOut(): void
    {
        try {
            AuthPolicy::fromMap(['principle' => 'service']);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security.auth configuration carries the unknown knob 'principle'"
                . ' (known knobs: enabled, tokens, principal, scope, required_scope,'
                . ' allow_anonymous, replay_capacity, replay_window).',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @dataProvider provideTheTokenShapeBoundsCases
     *
     * @param non-empty-string $message the rejection's expected prefix
     */
    #[DataProvider('provideTheTokenShapeBoundsCases')]
    public function testTheTokenShapeBounds(mixed $tokens, string $message): void
    {
        try {
            AuthPolicy::fromMap(['tokens' => $tokens]);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertStringStartsWith($message, $httpException->getMessage());
        }
    }

    /**
     * @return iterable<non-empty-string, array{mixed, non-empty-string}>
     */
    public static function provideTheTokenShapeBoundsCases(): iterable
    {
        yield 'a below-floor token' => [
            ['short-token-of-ten-chars'],
            'The security.auth tokens knob carries a token below the 32-character floor:',
        ];

        yield 'an over-ceiling token' => [
            [str_repeat('t', 513)],
            'The security.auth tokens knob carries a token above the 512-character ceiling.',
        ];

        yield 'a malformed token' => [
            ['this token carries spaces and commas, no good'],
            'The security.auth tokens knob carries a token that is not a bearer credential',
        ];

        yield 'a non-string entry' => [
            [7],
            'The security.auth tokens knob must be a list of strings, int given.',
        ];

        yield 'a non-list value' => [
            7,
            'The security.auth tokens knob must be a list of strings or a comma-separated string, int given.',
        ];
    }

    /**
     * @dataProvider provideTheFieldBoundsCases
     *
     * @param non-empty-string $message the rejection's expected prefix
     */
    #[DataProvider('provideTheFieldBoundsCases')]
    public function testTheFieldBounds(string $knob, mixed $value, string $message): void
    {
        try {
            AuthPolicy::fromMap([$knob => $value]);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }

    /**
     * @return iterable<non-empty-string, array{string, mixed, non-empty-string}>
     */
    public static function provideTheFieldBoundsCases(): iterable
    {
        yield 'an empty principal' => [
            'principal',
            '',
            "The security.auth principal knob must be a non-empty string of at most 128 characters, '' given.",
        ];

        yield 'an over-long principal' => [
            'principal',
            str_repeat('p', 129),
            'The security.auth principal knob must be a non-empty string of at most 128 characters, '
            . "'" . str_repeat('p', 129) . "' given.",
        ];

        yield 'a non-string principal' => [
            'principal',
            true,
            'The security.auth principal knob must be a non-empty string of at most 128 characters, bool given.',
        ];

        yield 'an empty scope' => [
            'scope',
            '',
            "The security.auth scope knob must be a non-empty string of at most 128 characters, '' given.",
        ];

        yield 'an over-long required scope' => [
            'required_scope',
            str_repeat('s', 129),
            'The security.auth required_scope knob must be a non-empty string of at most 128 characters, '
            . "'" . str_repeat('s', 129) . "' given.",
        ];
    }

    /**
     * @dataProvider provideTheReplayRangeCases
     */
    #[DataProvider('provideTheReplayRangeCases')]
    public function testTheReplayRange(string $knob, mixed $value, string $message): void
    {
        try {
            AuthPolicy::fromMap([$knob => $value]);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }

    /**
     * @return iterable<non-empty-string, array{string, mixed, string}>
     */
    public static function provideTheReplayRangeCases(): iterable
    {
        yield 'a zero capacity' => [
            'replay_capacity',
            0,
            'The security.auth replay_capacity knob must be 1 or above.',
        ];

        yield 'a zero window' => ['replay_window', 0, 'The security.auth replay_window knob must be 1 or above.'];

        yield 'a negative capacity' => [
            'replay_capacity',
            -1,
            'The security.auth replay_capacity knob must be 1 or above.',
        ];
    }

    public function testATokenOfExactlyTheFloorCarries(): void
    {
        $authPolicy = AuthPolicy::fromMap(['tokens' => [str_repeat('t', 32)]]);

        self::assertSame([str_repeat('t', 32)], $authPolicy->tokens);
    }

    public function testATokenOneBelowTheFloorIsRefused(): void
    {
        try {
            AuthPolicy::fromMap(['tokens' => [str_repeat('t', 31)]]);
            self::fail('The entropy floor must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth tokens knob carries a token below the 32-character floor:'
                . ' static bearer tokens are brute-forceable below it.',
                $httpException->getMessage(),
            );
        }
    }

    public function testATokenOfExactlyTheCeilingCarries(): void
    {
        $authPolicy = AuthPolicy::fromMap(['tokens' => [str_repeat('t', 512)]]);

        self::assertSame([str_repeat('t', 512)], $authPolicy->tokens);
    }

    public function testTheCommaSpellingReadsPastAMiddleGap(): void
    {
        $authPolicy = AuthPolicy::fromMap(['tokens' => self::TOKEN . ' ,, ' . self::SECOND_TOKEN]);

        self::assertSame(
            [self::TOKEN, self::SECOND_TOKEN],
            $authPolicy->tokens,
            'an empty entry between two tokens is cosmetic, not a terminator',
        );
    }

    public function testTheHandConstructionRangeHolds(): void
    {
        try {
            new AuthPolicy(replayCapacity: 0);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth replay_capacity knob must be 1 or above.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheHandConstructionWindowHolds(): void
    {
        try {
            new AuthPolicy(replayWindow: 0);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth replay_window knob must be 1 or above.', $httpException->getMessage());
        }
    }

    public function testTheUsableTokensSeamHaltsOnTheEmptyTable(): void
    {
        try {
            AuthBounds::assertUsableTokens([]);
            self::fail('An armed family with an empty table must halt the resolution.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth family is enabled but carries no static bearer tokens:'
                . ' provide at least one through the environment overlay'
                . ' (APP_SECURITY__AUTH__TOKENS, comma-separated, each 32 characters or above)'
                . ' or a configuration file, or disarm the family explicitly'
                . ' (security.auth enabled = false).',
                $httpException->getMessage(),
            );
        }
    }
}
