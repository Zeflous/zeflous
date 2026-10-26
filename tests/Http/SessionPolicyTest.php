<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\HttpFamilies;
use Zef\Framework\Http\SameSiteVocabulary;
use Zef\Framework\Http\SessionBounds;
use Zef\Framework\Http\SessionCookieName;
use Zef\Framework\Http\SessionKnobs;
use Zef\Framework\Http\SessionPolicy;
use Zef\Framework\Http\StrictIntegers;
use Zef\Framework\Http\StrictKnobs;
use Zef\Framework\Http\StrictToggles;

/**
 * The session family's knob reading, on its own terms: the default
 * posture (armed, the roadmap's promised secure trio -- HttpOnly,
 * Secure, SameSite=Lax -- and the thirty-minute idle timeout), the
 * full knob map, the environment's string spellings, the unknown-key
 * census, the `http` map's own family census -- plus the profile
 * mirror the compression family pinned the same way.
 *
 * @internal
 */
#[CoversClass(SessionPolicy::class)]
#[CoversClass(SessionKnobs::class)]
#[CoversClass(SameSiteVocabulary::class)]
#[CoversClass(SessionBounds::class)]
#[CoversClass(SessionCookieName::class)]
#[CoversClass(HttpFamilies::class)]
#[CoversClass(StrictKnobs::class)]
#[CoversClass(StrictToggles::class)]
#[CoversClass(StrictIntegers::class)]
final class SessionPolicyTest extends TestCase
{
    public function testAnAbsentFamilyReadsTheDefaultPosture(): void
    {
        $sessionPolicy = SessionPolicy::fromMap(null);

        self::assertTrue($sessionPolicy->enabled, 'the family is armed by default');
        self::assertSame('zef_session', $sessionPolicy->cookieName);
        self::assertSame(1800, $sessionPolicy->idleTimeout, 'thirty minutes, the promised default');
        self::assertSame(32, $sessionPolicy->idBytes);
        self::assertTrue($sessionPolicy->cookieSecure, 'the roadmap promised Secure armed by default');
        self::assertTrue($sessionPolicy->cookieHttpOnly, 'the roadmap promised HttpOnly by default');
        self::assertSame('Lax', $sessionPolicy->sameSite, 'the roadmap promised SameSite=Lax by default');
    }

    public function testTheFullKnobMapReads(): void
    {
        $sessionPolicy = SessionPolicy::fromMap([
            'enabled' => false,
            'cookie_name' => 'shop_session',
            'idle_timeout' => 900,
            'id_bytes' => 48,
            'cookie_secure' => false,
            'cookie_http_only' => false,
            'same_site' => 'Strict',
        ]);

        self::assertFalse($sessionPolicy->enabled);
        self::assertSame('shop_session', $sessionPolicy->cookieName);
        self::assertSame(900, $sessionPolicy->idleTimeout);
        self::assertSame(48, $sessionPolicy->idBytes);
        self::assertFalse($sessionPolicy->cookieSecure);
        self::assertFalse($sessionPolicy->cookieHttpOnly);
        self::assertSame('Strict', $sessionPolicy->sameSite);
    }

    public function testTheEnvironmentStringSpellingsFold(): void
    {
        $sessionPolicy = SessionPolicy::fromMap([
            'idle_timeout' => '900',
            'id_bytes' => '48',
            'enabled' => 'false',
            'cookie_secure' => 'true',
        ]);

        self::assertFalse($sessionPolicy->enabled);
        self::assertSame(900, $sessionPolicy->idleTimeout);
        self::assertSame(48, $sessionPolicy->idBytes);
        self::assertTrue($sessionPolicy->cookieSecure);
    }

    public function testAnEmptyMapReadsTheDefaultPosture(): void
    {
        $sessionPolicy = SessionPolicy::fromMap([]);

        self::assertTrue($sessionPolicy->enabled, "the absent knobs read the defaults, not the constructor's");
        self::assertSame('zef_session', $sessionPolicy->cookieName);
        self::assertSame(1800, $sessionPolicy->idleTimeout);
        self::assertSame(32, $sessionPolicy->idBytes);
        self::assertTrue($sessionPolicy->cookieSecure);
        self::assertTrue($sessionPolicy->cookieHttpOnly);
        self::assertSame('Lax', $sessionPolicy->sameSite);
    }

    public function testTheMapMustBeAMap(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('The http.session configuration must be a map, string given.');

        SessionPolicy::fromMap('zef_session');
    }

    public function testAnUnknownKnobHaltsTheBoot(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            "The http.session configuration carries the unknown knob 'same_sitee'"
            . ' (known knobs: enabled, cookie_name, idle_timeout, id_bytes, cookie_secure,'
            . ' cookie_http_only, same_site).',
        );

        SessionPolicy::fromMap(['same_sitee' => 'Strict']);
    }

    public function testAWrongTypedKnobHaltsTheBoot(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The http.session idle_timeout knob must be an integer or an all-digit string, float given.',
        );

        SessionPolicy::fromMap(['idle_timeout' => 10.5]);
    }

    #[DataProvider('provideAnOutOfBoundKnobHaltsTheBootCases')]
    public function testAnOutOfBoundKnobHaltsTheBoot(string $knob, int $value, string $spelled): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains($spelled);

        SessionPolicy::fromMap([$knob => $value]);
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function provideAnOutOfBoundKnobHaltsTheBootCases(): iterable
    {
        yield 'idle timeout below one second' => [
            'idle_timeout',
            0,
            'The http.session idle_timeout knob must be between 1 and 2592000, 0 given.',
        ];

        yield 'idle timeout above thirty days' => [
            'idle_timeout',
            2592001,
            'The http.session idle_timeout knob must be between 1 and 2592000, 2592001 given.',
        ];

        yield 'identifier entropy below the floor' => [
            'id_bytes',
            15,
            'The http.session id_bytes knob must be between 16 and 64, 15 given.',
        ];

        yield 'identifier entropy above the ceiling' => [
            'id_bytes',
            65,
            'The http.session id_bytes knob must be between 16 and 64, 65 given.',
        ];
    }

    public function testTheBoundCorridorEdgesRead(): void
    {
        $floors = SessionPolicy::fromMap(['idle_timeout' => 1, 'id_bytes' => 16]);
        self::assertSame(1, $floors->idleTimeout);
        self::assertSame(16, $floors->idBytes);

        $ceilings = SessionPolicy::fromMap(['idle_timeout' => 2592000, 'id_bytes' => 64]);
        self::assertSame(2592000, $ceilings->idleTimeout);
        self::assertSame(64, $ceilings->idBytes);
    }

    public function testDirectConstructionHoldsTheCorridorsToo(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The http.session idle_timeout knob must be between 1 and 2592000, 0 given.',
        );

        new SessionPolicy(idleTimeout: 0);
    }

    public function testDirectConstructionHoldsTheEntropyCorridorToo(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The http.session id_bytes knob must be between 16 and 64, 65 given.',
        );

        new SessionPolicy(idleTimeout: 1800, idBytes: 65);
    }

    public function testDirectConstructionHoldsTheCookieNameVocabularyToo(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            "The http.session cookie_name knob must be a cookie name (RFC 9110 token), 'zef session' given.",
        );

        new SessionPolicy(cookieName: 'zef session');
    }

    #[DataProvider('provideTheCookieNameGrammarHoldsCases')]
    public function testTheCookieNameGrammarHolds(mixed $cookieName, string $spelled): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains($spelled);

        SessionPolicy::fromMap(['cookie_name' => $cookieName]);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function provideTheCookieNameGrammarHoldsCases(): iterable
    {
        yield 'a space is not a token character' => [
            'zef session',
            "The http.session cookie_name knob must be a cookie name (RFC 9110 token), 'zef session' given.",
        ];

        yield 'a semicolon is the attribute separator' => [
            'zef;session',
            "The http.session cookie_name knob must be a cookie name (RFC 9110 token), 'zef;session' given.",
        ];

        yield 'an empty name is not a token' => [
            '',
            "The http.session cookie_name knob must be a cookie name (RFC 9110 token), '' given.",
        ];

        yield 'a non-string name is refused by type' => [
            42,
            'The http.session cookie_name knob must be a cookie name (RFC 9110 token), int given.',
        ];
    }

    public function testTheSameSiteVocabularyFoldsCaseInsensitively(): void
    {
        self::assertSame('Strict', SameSiteVocabulary::read('strict', 'http.session'));
        self::assertSame('Lax', SameSiteVocabulary::read('LAX', 'http.session'));
        self::assertSame('None', SameSiteVocabulary::read('none', 'http.session'));
    }

    public function testTheSameSiteVocabularyRejectsAnUnknownWord(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The http.session same_site knob must be one of the SameSite vocabulary'
            . " (Strict, Lax, None), 'Laxy' given.",
        );

        SessionPolicy::fromMap(['same_site' => 'Laxy']);
    }

    public function testTheSameSiteVocabularyRejectsANonString(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The http.session same_site knob must be one of the SameSite vocabulary'
            . ' (Strict, Lax, None), int given.',
        );

        SessionPolicy::fromMap(['same_site' => 1]);
    }

    public function testTheNonePostureRequiresTheSecureKnob(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The http.session same_site None knob requires the Secure cookie attribute (cookie_secure).',
        );

        SessionPolicy::fromMap([
            'same_site' => 'None',
            'cookie_secure' => false,
        ]);
    }

    public function testTheNonePostureReadsWhenSecureIsArmed(): void
    {
        $sessionPolicy = SessionPolicy::fromMap(['same_site' => 'None']);

        self::assertSame('None', $sessionPolicy->sameSite);
        self::assertTrue($sessionPolicy->cookieSecure);
    }

    public function testTheHttpMapCensusRejectsAnUnknownFamily(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            "The http configuration carries the unknown family 'sessio'"
            . ' (known families: error_handling, compression, body_parser, session).',
        );

        HttpFamilies::subtree(['error_handling' => [], 'sessio' => []], 'session');
    }

    public function testTheHttpMapMustBeAMap(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('The http configuration must be a map, int given.');

        HttpFamilies::subtree(7, 'session');
    }

    public function testAnAbsentHttpMapReadsTheAbsentFamily(): void
    {
        self::assertNull(HttpFamilies::subtree(null, 'session'));
    }

    public function testAnAbsentFamilyInsideTheMapReadsTheDefaultPosture(): void
    {
        $sessionPolicy = SessionPolicy::fromMap(
            HttpFamilies::subtree(['error_handling' => ['debug' => false]], 'session'),
        );

        self::assertTrue($sessionPolicy->enabled);
        self::assertSame(1800, $sessionPolicy->idleTimeout);
    }

    public function testTheProfileDeclarationMirrorsThePolicyDefaults(): void
    {
        $profile = require ZEF_FRAMEWORK_ROOT . '/app/configs/http.php';
        self::assertIsArray($profile, 'the shipped profile is a plain PHP map');
        self::assertArrayHasKey('session', $profile);

        self::assertSame(
            [
                'enabled' => true,
                'cookie_name' => 'zef_session',
                'idle_timeout' => 1800,
                'id_bytes' => 32,
                'cookie_secure' => true,
                'cookie_http_only' => true,
                'same_site' => 'Lax',
            ],
            $profile['session'],
            "the shipped profile spells the policy's own default posture, so the two cannot drift",
        );
    }
}
