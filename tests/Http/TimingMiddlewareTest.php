<?php

declare(strict_types=1);

namespace Zef\Test;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ResponseTime;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\TimingMiddleware;
use Zef\Framework\Http\TimingPolicy;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\SteppingClock;
use Zef\Test\Support\Http\ThrowingHandler;

/**
 * The response-time boundary, on its own terms: the measured span
 * the frozen clock answers as an exact two-decimal header, the
 * replace-not-append stamp, the verbatim rest-of-response, the
 * disarmed and the inert pass-throughs (the inert one proven
 * clock-less), the handler failure that propagates untouched, and
 * the method-independence of the measurement -- plus the broken
 * clock seam's negative delta the clamp absorbs.
 *
 * @internal
 */
#[CoversClass(TimingMiddleware::class)]
#[CoversClass(ResponseTime::class)]
final class TimingMiddlewareTest extends TestCase
{
    private FixedResponseHandler $fixedResponseHandler;

    private SteppingClock $steppingClock;

    #[Override]
    protected function setUp(): void
    {
        $this->fixedResponseHandler = new FixedResponseHandler(
            new Response(200, ['Content-Type' => 'text/plain'], 'answered'),
        );
        $this->steppingClock = new SteppingClock(epoch: 1_000_000_000, step: 250_000);
    }

    public function testAnArmedFamilyStampsTheFrozenSpan(): void
    {
        $response = $this->middleware(emitHeader: true)->process(
            $this->request('GET'),
            $this->fixedResponseHandler,
        );

        self::assertSame('0.25ms', $response->getHeaderLine('X-Response-Time'));
        self::assertSame(['0.25ms'], $response->getHeader('X-Response-Time'));
        self::assertSame(2, $this->steppingClock->readCount(), 'one span, two clock readings');
    }

    public function testTheStampReplacesWhateverTheInnerPipelineLeft(): void
    {
        $fixedResponseHandler = new FixedResponseHandler(
            new Response(200, ['X-Response-Time' => '9.99ms'], 'answered'),
        );

        $response = $this->middleware(emitHeader: true)->process($this->request('GET'), $fixedResponseHandler);

        self::assertSame('0.25ms', $response->getHeaderLine('X-Response-Time'));
        self::assertSame(['0.25ms'], $response->getHeader('X-Response-Time'), 'replace, never append');
    }

    public function testTheStampLeavesTheRestOfTheResponseVerbatim(): void
    {
        $response = $this->middleware(emitHeader: true)->process(
            $this->request('GET'),
            $this->fixedResponseHandler,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('answered', (string) $response->getBody());
    }

    public function testTheMeasurementCarriesEveryMethodEqually(): void
    {
        foreach (['HEAD', 'POST', 'OPTIONS'] as $method) {
            $response = $this->middleware(emitHeader: true)
                ->process($this->request($method), $this->fixedResponseHandler)
            ;

            self::assertSame('0.25ms', $response->getHeaderLine('X-Response-Time'), $method);
        }
    }

    public function testADisarmedFamilyIsAPassThrough(): void
    {
        $response = $this->middleware(enabled: false, emitHeader: true)
            ->process($this->request('GET'), $this->fixedResponseHandler)
        ;

        self::assertFalse($response->hasHeader('X-Response-Time'));
        self::assertSame(0, $this->steppingClock->readCount(), 'a disarmed family never measures');
    }

    public function testAnInertFamilyNeverReadsTheClock(): void
    {
        $response = $this->middleware()->process($this->request('GET'), $this->fixedResponseHandler);

        self::assertFalse($response->hasHeader('X-Response-Time'));
        self::assertSame(0, $this->steppingClock->readCount(), 'no disclosure, no measurement');
    }

    public function testAHandlerFailurePropagatesUntouched(): void
    {
        $failure = new RuntimeException('the handler failed');

        try {
            $this->middleware(emitHeader: true)->process($this->request('GET'), new ThrowingHandler($failure));
            self::fail('The handler failure must propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($failure, $runtimeException, 'the failure itself, never a wrapped one');
        }
    }

    public function testAZeroSpanKeepsTheTwoDecimalShape(): void
    {
        $this->steppingClock = new SteppingClock(epoch: 1_000_000_000, step: 0);

        $response = $this->middleware(emitHeader: true)->process(
            $this->request('GET'),
            $this->fixedResponseHandler,
        );

        self::assertSame('0.00ms', $response->getHeaderLine('X-Response-Time'));
    }

    public function testABrokenClockSeamsNegativeDeltaClampsToZero(): void
    {
        $this->steppingClock = new SteppingClock(epoch: 1_000_000_000, step: -250_000);

        $response = $this->middleware(emitHeader: true)->process(
            $this->request('GET'),
            $this->fixedResponseHandler,
        );

        self::assertSame('0.00ms', $response->getHeaderLine('X-Response-Time'));
    }

    /**
     * The family's middleware over the frozen clock.
     */
    private function middleware(bool $enabled = true, bool $emitHeader = false): TimingMiddleware
    {
        return new TimingMiddleware(
            new TimingPolicy(enabled: $enabled, emitHeader: $emitHeader),
            $this->steppingClock->now(),
        );
    }

    /**
     * One request through the family's own server request object.
     */
    private function request(string $method): ServerRequest
    {
        return new ServerRequest($method, new Uri('https://app.example.com/health'));
    }
}
