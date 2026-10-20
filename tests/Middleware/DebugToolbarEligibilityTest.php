<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\DebugToolbarEligibility;
use Zef\Test\Support\Http\LyingSizeStream;

/**
 * Pins the toolbar eligibility's own guard table at the unit seam: the
 * status window's both edges, the body budget's both edges -- every bound
 * the middleware's battery composes but never isolates on a document that
 * could actually be spliced. The large-document refusal in the composed
 * battery travels on a body with no closing tag, so the injector's own
 * refusal answers there regardless of the size arm; this battery reads the
 * arm itself, over documents that carry the tag the injector needs.
 *
 * @internal
 */
#[CoversClass(DebugToolbarEligibility::class)]
final class DebugToolbarEligibilityTest extends TestCase
{
    public function testAllowsAnEligibleHtmlResponse(): void
    {
        self::assertTrue(DebugToolbarEligibility::allows(
            $this->request(),
            $this->htmlResponse(200, '<html><body>hello</body></html>'),
        ));
    }

    /**
     * The status window is half-open: two hundred and ninety-nine is the
     * last status the toolbar may decorate, and three hundred -- the first
     * redirection -- is the first it must not, even though the body, the
     * content type and every other arm stay eligible.
     */
    public function testRefusesTheFirstRedirectionStatus(): void
    {
        self::assertTrue(DebugToolbarEligibility::allows(
            $this->request(),
            $this->htmlResponse(299, '<html><body>last success</body></html>'),
        ));
        self::assertFalse(DebugToolbarEligibility::allows(
            $this->request(),
            $this->htmlResponse(300, '<html><body>multiple choices</body></html>'),
        ));
    }

    /**
     * The body budget is inclusive at the ceiling: a document of exactly
     * two million bytes is still decorated, and the two-million-and-first
     * byte is the first the budget refuses -- over a document that carries
     * the closing tag, so the refusal can only come from the budget arm.
     */
    public function testBoundsTheDecoratedBodyAtTwoMillionBytes(): void
    {
        $atTheBudget = '<html><body>' . str_repeat('x', 2_000_000 - 26) . '</body></html>';
        $pastTheBudget = '<html><body>' . str_repeat('x', 2_000_001 - 26) . '</body></html>';

        self::assertSame(2_000_000, mb_strlen($atTheBudget));
        self::assertSame(2_000_001, mb_strlen($pastTheBudget));
        self::assertTrue(DebugToolbarEligibility::allows($this->request(), $this->htmlResponse(200, $atTheBudget)));
        self::assertFalse(DebugToolbarEligibility::allows($this->request(), $this->htmlResponse(200, $pastTheBudget)));
    }

    /**
     * A stream that cannot state its size answers the unknown arm: the
     * coalesced fallback is the ceiling the budget can never admit, so an
     * unknown size is refused rather than guessed.
     */
    public function testRefusesABodyOfUnknownSize(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'text/html'],
            new LyingSizeStream(Stream::fromString('<html><body>opaque</body></html>'), null),
        );

        self::assertFalse(DebugToolbarEligibility::allows($this->request(), $response));
    }

    private function request(): ServerRequest
    {
        $request = new ServerRequest('GET', new Uri('https://app.example.test/'))
            ->withHeader('Accept', 'text/html')
        ;
        self::assertInstanceOf(ServerRequest::class, $request);

        return $request;
    }

    private function htmlResponse(int $status, string $body): Response
    {
        return new Response($status, ['Content-Type' => 'text/html'], $body);
    }
}
