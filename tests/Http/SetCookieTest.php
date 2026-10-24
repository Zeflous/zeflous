<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\SetCookie;

/**
 * The stateless cookie helper's response half, on its own terms: the
 * Secure Cookie Builder the Zero-Library Shim promised -- the
 * security flags right by default, the Secure attribute's
 * scheme-conditional emission (and the `SameSite=None` exception the
 * browser contract forces), the attribute order the renderer spells,
 * the deletion cookie -- and every grammar the constructor holds.
 *
 * @internal
 */
#[CoversClass(SetCookie::class)]
final class SetCookieTest extends TestCase
{
    public function testTheDefaultsRenderThePromisedSecureTrio(): void
    {
        $setCookie = new SetCookie('theme', 'dark');

        self::assertSame(
            'theme=dark; Path=/; SameSite=Lax; HttpOnly',
            $setCookie->headerValue('http'),
            'the defaults are Path=/, SameSite=Lax, HttpOnly -- Secure stays scheme-conditional',
        );
    }

    public function testTheSecureAttributeRidesTheHttpsScheme(): void
    {
        $setCookie = new SetCookie('theme', 'dark');

        self::assertSame(
            'theme=dark; Path=/; SameSite=Lax; Secure; HttpOnly',
            $setCookie->headerValue('https'),
        );
    }

    public function testTheNonePostureCarriesSecureWhateverTheScheme(): void
    {
        $setCookie = new SetCookie('theme', 'dark', sameSite: SetCookie::SAME_SITE_NONE);

        self::assertSame(
            'theme=dark; Path=/; SameSite=None; Secure; HttpOnly',
            $setCookie->headerValue('http'),
            'no current browser accepts a None cookie without Secure',
        );
    }

    public function testADisarmedSecureFlagNeverEmits(): void
    {
        $setCookie = new SetCookie('prefs', 'lang=id', secure: false);

        self::assertSame(
            'prefs=lang=id; Path=/; SameSite=Lax; HttpOnly',
            $setCookie->headerValue('https'),
            'a host that disarmed Secure declared the http-tolerant posture',
        );
    }

    public function testADisarmedHttpOnlyFlagDropsTheAttribute(): void
    {
        $setCookie = new SetCookie('theme', 'dark', httpOnly: false);

        self::assertSame(
            'theme=dark; Path=/; SameSite=Lax',
            $setCookie->headerValue('http'),
            'a cookie JavaScript must read is a declaration, not an accident',
        );
    }

    public function testTheOptionalAttributesRenderInTheirOwnOrder(): void
    {
        $setCookie = new SetCookie(
            'session',
            'AQ',
            path: '/shop',
            domain: 'app.example.com',
            maxAge: 3600,
            expiresAt: 1767225600,
        );

        self::assertSame(
            'session=AQ; Domain=app.example.com; Path=/shop; Expires=Thu, 01 Jan 2026 00:00:00 GMT;'
            . ' Max-Age=3600; SameSite=Lax; Secure; HttpOnly',
            $setCookie->headerValue('https'),
        );
    }

    public function testAnEmptyPathLeavesTheAttributeOut(): void
    {
        $setCookie = new SetCookie('theme', 'dark', path: '');

        self::assertSame(
            'theme=dark; SameSite=Lax; HttpOnly',
            $setCookie->headerValue('http'),
        );
    }

    public function testTheDeletionCookieRetiresTheNameOnEveryBrowser(): void
    {
        $cookie = SetCookie::deleted('zef_session');

        self::assertSame(
            'zef_session=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; SameSite=Lax; HttpOnly',
            $cookie->headerValue('http'),
        );
    }

    public function testTheDeletionCookieCarriesTheFlagsItWasGiven(): void
    {
        $cookie = SetCookie::deleted('zef_session', SetCookie::SAME_SITE_NONE);

        self::assertSame(
            'zef_session=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; SameSite=None; Secure; HttpOnly',
            $cookie->headerValue('http'),
        );
    }

    /** @param Closure(): mixed $spelled */
    #[DataProvider('provideTheConstructorHoldsItsGrammarsCases')]
    public function testTheConstructorHoldsItsGrammars(Closure $spelled, string $message): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $spelled();
    }

    /** @return iterable<string, array{Closure(): mixed, string}> */
    public static function provideTheConstructorHoldsItsGrammarsCases(): iterable
    {
        yield 'a non-token name' => [
            static fn (): mixed => new SetCookie('zef session'),
            "The cookie name must be an RFC 9110 token, 'zef session' given.",
        ];

        yield 'a semicolon inside the value' => [
            static fn (): mixed => new SetCookie('theme', 'dark;light'),
            'The cookie value must match the cookie-octet grammar'
            . ' (no quotes, commas, semicolons, backslashes or control bytes).',
        ];

        yield 'a double quote inside the value' => [
            static fn (): mixed => new SetCookie('theme', '"dark"'),
            'The cookie value must match the cookie-octet grammar'
            . ' (no quotes, commas, semicolons, backslashes or control bytes).',
        ];

        yield 'a control byte inside the value' => [
            static fn (): mixed => new SetCookie('theme', "dark\x00"),
            'The cookie value must match the cookie-octet grammar'
            . ' (no quotes, commas, semicolons, backslashes or control bytes).',
        ];

        yield 'a non-root-relative path' => [
            static fn (): mixed => new SetCookie('theme', 'dark', path: 'shop'),
            "The cookie path must be root-relative or empty, 'shop' given.",
        ];

        yield 'an underscore-bearing domain' => [
            static fn (): mixed => new SetCookie('theme', 'dark', domain: 'app_example.com'),
            "The cookie domain must be a dot-separated domain or empty, 'app_example.com' given.",
        ];

        yield 'a lowercase SameSite word' => [
            static fn (): mixed => new SetCookie('theme', 'dark', sameSite: 'lax'),
            "The cookie sameSite must be one of the SameSite vocabulary (Strict, Lax, None), 'lax' given.",
        ];

        yield 'a typo of the SameSite vocabulary' => [
            static fn (): mixed => new SetCookie('theme', 'dark', sameSite: 'Laxy'),
            "The cookie sameSite must be one of the SameSite vocabulary (Strict, Lax, None), 'Laxy' given.",
        ];

        yield 'a negative Max-Age' => [
            static fn (): mixed => new SetCookie('theme', 'dark', maxAge: -1),
            'The cookie maxAge must not be negative, -1 given.',
        ];

        yield 'a negative Expires timestamp' => [
            static fn (): mixed => new SetCookie('theme', 'dark', expiresAt: -1),
            'The cookie expiresAt must not be negative, -1 given.',
        ];
    }

    public function testTheValueGrammarIsTheSharedPublicCheck(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The cookie value must match the cookie-octet grammar'
            . ' (no quotes, commas, semicolons, backslashes or control bytes).',
        );

        SetCookie::assertValueGrammar('id;en');
    }
}
