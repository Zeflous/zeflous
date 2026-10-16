<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\CsrfBounds;
use Zef\Framework\Http\CsrfKnobs;
use Zef\Framework\Http\CsrfPolicy;
use Zef\Framework\Http\CsrfSameSite;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\StrictIntegers;
use Zef\Framework\Http\StrictToggles;

/**
 * The CSRF family's knob reading, on its own terms: the default posture
 * (the reference's fail-safe default-on), the strict spellings (native
 * and environment-string alike), the SameSite vocabulary, the field-name
 * grammar, the fail-closed rejections -- and the drift pin against the
 * framework profile's own declaration.
 *
 * @internal
 */
#[CoversClass(FrameworkDefaults::class)]
#[CoversClass(CsrfBounds::class)]
#[CoversClass(CsrfKnobs::class)]
#[CoversClass(CsrfPolicy::class)]
#[CoversClass(CsrfSameSite::class)]
#[CoversClass(StrictIntegers::class)]
#[CoversClass(StrictToggles::class)]
final class CsrfPolicyTest extends TestCase
{
    public function testTheAbsentFamilyReadsTheDefaultPosture(): void
    {
        $policy = CsrfPolicy::fromMap(null);

        self::assertTrue($policy->enabled, "the reference's fail-safe default-on");
        self::assertSame('', $policy->secret, 'no secret ships in the profile');
        self::assertSame('ZEF-XSRF-TOKEN', $policy->cookieName);
        self::assertSame('X-CSRF-Token', $policy->headerName);
        self::assertSame(32, $policy->tokenBytes);
        self::assertTrue($policy->cookieSecure);
        self::assertTrue($policy->cookieHttpOnly);
        self::assertSame(CsrfSameSite::STRICT, $policy->sameSite);
    }

    public function testTheEmptyMapReadsTheDefaultPosture(): void
    {
        self::assertSame(
            $this->fieldMap(CsrfPolicy::fromMap(null)),
            $this->fieldMap(CsrfPolicy::fromMap([])),
        );
    }

    public function testTheNativeKnobsAreReadVerbatim(): void
    {
        $policy = CsrfPolicy::fromMap([
            'enabled' => true,
            'secret' => 'native-file-secret-of-at-least-32-bytes!!',
            'cookie_name' => 'APP-CSRF',
            'header_name' => 'X-App-Csrf',
            'token_bytes' => 64,
            'cookie_secure' => false,
            'cookie_http_only' => false,
            'same_site' => 'Lax',
        ]);

        self::assertTrue($policy->enabled);
        self::assertSame('native-file-secret-of-at-least-32-bytes!!', $policy->secret);
        self::assertSame('APP-CSRF', $policy->cookieName);
        self::assertSame('X-App-Csrf', $policy->headerName);
        self::assertSame(64, $policy->tokenBytes);
        self::assertFalse($policy->cookieSecure);
        self::assertFalse($policy->cookieHttpOnly);
        self::assertSame('Lax', $policy->sameSite);
    }

    public function testTheEnvironmentSpellingsFoldThroughTheStrictReadings(): void
    {
        $policy = CsrfPolicy::fromMap([
            'enabled' => 'on',
            'secret' => 'overlay-secret-of-at-least-32-bytes!!!',
            'token_bytes' => '16',
            'cookie_secure' => 'off',
            'cookie_http_only' => 'no',
            'same_site' => 'lAx',
        ]);

        self::assertTrue($policy->enabled);
        self::assertSame('overlay-secret-of-at-least-32-bytes!!!', $policy->secret);
        self::assertSame(16, $policy->tokenBytes);
        self::assertFalse($policy->cookieSecure);
        self::assertFalse($policy->cookieHttpOnly);
        self::assertSame(CsrfSameSite::LAX, $policy->sameSite, 'the vocabulary folds case-insensitively');
    }

    public function testTheSameSiteVocabularyFoldsEverySpelling(): void
    {
        self::assertSame(CsrfSameSite::STRICT, CsrfSameSite::read('strict'));
        self::assertSame(CsrfSameSite::LAX, CsrfSameSite::read('LAX'));
        self::assertSame(CsrfSameSite::NONE, CsrfSameSite::read('None'));
        self::assertSame(CsrfSameSite::STRICT, CsrfSameSite::read('Strict'));
    }

    public function testTheFrameworkProfileMirrorsThePolicyDefaults(): void
    {
        $fromEmptyMap = CsrfPolicy::fromMap([]);
        $security = FrameworkDefaults::profile()['security'] ?? null;
        self::assertIsArray($security);
        $fromDefaults = $security['csrf'] ?? null;
        self::assertIsArray($fromDefaults);

        self::assertSame(
            [
                'enabled' => $fromEmptyMap->enabled,
                'secret' => $fromEmptyMap->secret,
                'cookie_name' => $fromEmptyMap->cookieName,
                'header_name' => $fromEmptyMap->headerName,
                'token_bytes' => $fromEmptyMap->tokenBytes,
                'cookie_secure' => $fromEmptyMap->cookieSecure,
                'cookie_http_only' => $fromEmptyMap->cookieHttpOnly,
                'same_site' => $fromEmptyMap->sameSite,
            ],
            $fromDefaults,
            'the Default layer and the policy constructor cannot drift apart',
        );
    }

    public function testAnArmedFamilyWithoutASecretHaltsWithTheRemediation(): void
    {
        try {
            CsrfBounds::assertUsableSecret('');
            self::fail('An armed family with no secret must halt the resolution.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.csrf family is enabled but carries no usable signing secret:'
                . ' provide one through the environment overlay (APP_SECURITY__CSRF__SECRET,'
                . ' at least 32 bytes) or a configuration file, or disarm the family explicitly'
                . ' (security.csrf enabled = false).',
                $httpException->getMessage(),
            );
        }
    }

    public function testAShortSecretHaltsWhenArmed(): void
    {
        try {
            CsrfBounds::assertUsableSecret(str_repeat('a', 31));
            self::fail('A secret below 32 bytes must halt the armed family.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.csrf family is enabled but carries no usable signing secret:'
                . ' provide one through the environment overlay (APP_SECURITY__CSRF__SECRET,'
                . ' at least 32 bytes) or a configuration file, or disarm the family explicitly'
                . ' (security.csrf enabled = false).',
                $httpException->getMessage(),
            );
        }
    }

    public function testAMultiByteSecretBelowThirtyTwoCharactersHalts(): void
    {
        try {
            CsrfBounds::assertUsableSecret(str_repeat('é', 31));
            self::fail('A secret below 32 characters must halt, whatever its byte length.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.csrf family is enabled but carries no usable signing secret:'
                . ' provide one through the environment overlay (APP_SECURITY__CSRF__SECRET,'
                . ' at least 32 bytes) or a configuration file, or disarm the family explicitly'
                . ' (security.csrf enabled = false).',
                $httpException->getMessage(),
            );
        }
    }

    public function testASecretOfExactlyThirtyTwoBytesIsUsable(): void
    {
        CsrfBounds::assertUsableSecret(str_repeat('a', 32));

        $policy = CsrfPolicy::fromMap(['secret' => str_repeat('a', 32)]);

        self::assertSame(32, mb_strlen($policy->secret), 'the floor admits exactly 32');
    }

    public function testADisarmedFamilyAcceptsAnUnusableSecret(): void
    {
        $policy = CsrfPolicy::fromMap(['enabled' => false, 'secret' => '']);

        self::assertFalse($policy->enabled);
        self::assertSame('', $policy->secret, 'the disarm is explicit, the secret unread');
    }

    /**
     * The policy is data, the resolution is the seam: an armed map with
     * an unusable secret still *reads* (the shipped profile carries
     * exactly this posture), and it is the factory the stack membership
     * triggers that halts -- the layering the boot test walks end to
     * end through the factory's own halt.
     */
    public function testAnArmedMapWithoutASecretStillReadsAsData(): void
    {
        $policy = CsrfPolicy::fromMap(['secret' => '']);

        self::assertTrue($policy->enabled, 'the reading is the data, not the arming');
        self::assertSame('', $policy->secret);
    }

    public function testTheEntropyFloorHaltsBelowSixteen(): void
    {
        try {
            CsrfPolicy::fromMap([
                'secret' => 'a-signing-secret-of-at-least-32-bytes!',
                'token_bytes' => 15,
            ]);
            self::fail('An entropy below 16 bytes must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.csrf token_bytes knob must be 16 or above.',
                $httpException->getMessage(),
            );
        }
    }

    public function testSameSiteNoneRequiresTheSecureAttribute(): void
    {
        try {
            CsrfPolicy::fromMap([
                'secret' => 'a-signing-secret-of-at-least-32-bytes!',
                'same_site' => 'None',
                'cookie_secure' => false,
            ]);
            self::fail('SameSite None without the Secure attribute must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.csrf same_site None knob requires the Secure cookie attribute'
                . ' (cookie_secure).',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @dataProvider provideTheStrictReadingsHaltWithTheKnobNamedCases
     *
     * @param array<array-key, mixed>|string $map
     */
    #[DataProvider('provideTheStrictReadingsHaltWithTheKnobNamedCases')]
    public function testTheStrictReadingsHaltWithTheKnobNamed(array|string $map, string $message): void
    {
        try {
            CsrfPolicy::fromMap($map);
            self::fail('The strict reading must halt: ' . $message);
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }

    /**
     * @return iterable<non-empty-string, array{array<array-key, mixed>|string, string}>
     */
    public static function provideTheStrictReadingsHaltWithTheKnobNamedCases(): iterable
    {
        $secret = 'a-signing-secret-of-at-least-32-bytes!';

        yield 'non-map subtree' => ['nope', 'The security.csrf configuration must be a map, string given.'];

        yield 'unknown knob' => [
            ['coookie_name' => 'APP-CSRF'],
            "The security.csrf configuration carries the unknown knob 'coookie_name'"
            . ' (known knobs: enabled, secret, cookie_name, header_name, token_bytes,'
            . ' cookie_secure, cookie_http_only, same_site).',
        ];

        yield 'unknown knob after a known one' => [
            ['enabled' => true, 'coookie_name' => 'APP-CSRF'],
            "The security.csrf configuration carries the unknown knob 'coookie_name'"
            . ' (known knobs: enabled, secret, cookie_name, header_name, token_bytes,'
            . ' cookie_secure, cookie_http_only, same_site).',
        ];

        yield 'non-string knob key' => [
            [7 => 'x'],
            "The security.csrf configuration carries the unknown knob 'int'"
            . ' (known knobs: enabled, secret, cookie_name, header_name, token_bytes,'
            . ' cookie_secure, cookie_http_only, same_site).',
        ];

        yield 'toggle neither bool nor spelling' => [
            ['enabled' => 'maybe', 'secret' => $secret],
            'The security.csrf enabled knob must be a boolean or one of the boolean string'
                . ' spellings (true/false, on/off, yes/no, 1/0), string given.',
        ];

        yield 'entropy neither int nor digits' => [
            ['secret' => $secret, 'token_bytes' => '16.5'],
            'The security.csrf token_bytes knob must be an integer or an all-digit string,'
                . ' string given.',
        ];

        yield 'secret not a string' => [
            ['secret' => true],
            'The security.csrf secret knob must be a string, bool given.',
        ];

        yield 'cookie name with a space' => [
            ['secret' => $secret, 'cookie_name' => 'APP CSRF'],
            'The security.csrf cookie_name knob must be a header-field name (RFC 9110 token),'
                . " 'APP CSRF' given.",
        ];

        yield 'cookie name with a separator byte' => [
            ['secret' => $secret, 'cookie_name' => 'APP,CSRF'],
            'The security.csrf cookie_name knob must be a header-field name (RFC 9110 token),'
                . " 'APP,CSRF' given.",
        ];

        yield 'header name with a space' => [
            ['secret' => $secret, 'header_name' => 'X App Csrf'],
            'The security.csrf header_name knob must be a header-field name (RFC 9110 token),'
                . " 'X App Csrf' given.",
        ];

        yield 'header name not a string' => [
            ['secret' => $secret, 'header_name' => 42],
            'The security.csrf header_name knob must be a header-field name (RFC 9110 token),'
                . ' int given.',
        ];

        yield 'same site outside the vocabulary' => [
            ['secret' => $secret, 'same_site' => 'Strictly'],
            'The security.csrf same_site knob must be one of the SameSite vocabulary'
                . " (Strict, Lax, None), 'Strictly' given.",
        ];

        yield 'same site not a string' => [
            ['secret' => $secret, 'same_site' => true],
            'The security.csrf same_site knob must be one of the SameSite vocabulary'
                . ' (Strict, Lax, None), bool given.',
        ];
    }

    /**
     * The hand-construction defaults carry the same grammar the reading
     * enforces: a hand-built name with a space is refused, not silently
     * honoured.
     */
    public function testHandConstructionHoldsTheNameGrammar(): void
    {
        try {
            new CsrfPolicy(
                secret: 'a-signing-secret-of-at-least-32-bytes!',
                cookieName: 'APP CSRF',
            );
            self::fail('A hand-constructed non-token cookie name must refuse the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.csrf cookie_name knob must be a header-field name (RFC 9110 token).',
                $httpException->getMessage(),
            );
        }

        try {
            new CsrfPolicy(
                secret: 'a-signing-secret-of-at-least-32-bytes!',
                headerName: 'X App Csrf',
            );
            self::fail('A hand-constructed non-token header name must refuse the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.csrf header_name knob must be a header-field name (RFC 9110 token).',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return array{enabled: bool, secret: string, cookieName: string, headerName: string,
     *               tokenBytes: int, cookieSecure: bool, cookieHttpOnly: bool, sameSite: string}
     */
    private function fieldMap(CsrfPolicy $csrfPolicy): array
    {
        return [
            'enabled' => $csrfPolicy->enabled,
            'secret' => $csrfPolicy->secret,
            'cookieName' => $csrfPolicy->cookieName,
            'headerName' => $csrfPolicy->headerName,
            'tokenBytes' => $csrfPolicy->tokenBytes,
            'cookieSecure' => $csrfPolicy->cookieSecure,
            'cookieHttpOnly' => $csrfPolicy->cookieHttpOnly,
            'sameSite' => $csrfPolicy->sameSite,
        ];
    }
}
