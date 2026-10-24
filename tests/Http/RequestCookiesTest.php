<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\RequestCookies;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;

/**
 * The stateless cookie helper's request half, on its own terms: the
 * fail-closed read over the request's own cookie bag (the vendored
 * pair's first src consumer), the symmetric write that folds one
 * name-value pair into a bag copy that carries every other cookie
 * untouched -- and the grammars both directions hold.
 *
 * @internal
 */
#[CoversClass(RequestCookies::class)]
final class RequestCookiesTest extends TestCase
{
    public function testAnAbsentNameAnswersNull(): void
    {
        self::assertNull(RequestCookies::value($this->request([]), 'zef_session'));
    }

    public function testACarriedNameAnswersItsStringValue(): void
    {
        self::assertSame(
            'theme=dark&lang=id',
            RequestCookies::value($this->request(['prefs' => 'theme=dark&lang=id']), 'prefs'),
        );
    }

    public function testANonStringValueRefusesFailClosed(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            "The request cookie bag carries a non-string value under the name 'zef_session' (int).",
        );

        RequestCookies::value($this->request(['zef_session' => 7]), 'zef_session');
    }

    public function testTheWriteFoldsOnePairIntoTheBag(): void
    {
        $decorated = RequestCookies::with($this->request(['theme' => 'dark']), 'lang', 'id');

        self::assertSame(
            ['theme' => 'dark', 'lang' => 'id'],
            $decorated->getCookieParams(),
            'the write carries every other cookie untouched',
        );
    }

    public function testTheWriteReplacesASameNamedValue(): void
    {
        $decorated = RequestCookies::with($this->request(['lang' => 'en', 'theme' => 'dark']), 'lang', 'id');

        self::assertSame(
            ['lang' => 'id', 'theme' => 'dark'],
            $decorated->getCookieParams(),
            'an existing value under the same name is replaced in its own position, exactly once',
        );
    }

    public function testTheWriteIsReadableBackThroughTheRead(): void
    {
        $decorated = RequestCookies::with($this->request([]), 'lang', 'id');

        self::assertSame('id', RequestCookies::value($decorated, 'lang'));
    }

    public function testTheWriteHoldsTheNameGrammar(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            "The cookie name must be an RFC 9110 token, 'zef session' given.",
        );

        RequestCookies::with($this->request([]), 'zef session', 'id');
    }

    public function testTheWriteHoldsTheValueGrammar(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The cookie value must match the cookie-octet grammar'
            . ' (no quotes, commas, semicolons, backslashes or control bytes).',
        );

        RequestCookies::with($this->request([]), 'lang', 'id;en');
    }

    /**
     * One request over the given cookie bag.
     *
     * @param array<string, mixed> $cookieParams
     */
    private function request(array $cookieParams): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('https://app.example.com/'),
            cookieParams: $cookieParams,
        );
    }
}
