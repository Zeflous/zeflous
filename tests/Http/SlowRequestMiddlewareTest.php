<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\SlowRequestMiddleware;
use Zef\Framework\Http\SlowRequestPolicy;
use Zef\Framework\Http\SlowRequestRecord;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\RawPathUri;
use Zef\Test\Support\Http\SteppingClock;
use Zef\Test\Support\Http\ThrowingHandler;
use Zef\Test\Support\Log\FailingLogger;
use Zef\Test\Support\Log\RecordingLogger;

/**
 * The total-duration boundary, on its own terms: the record a span
 * that strictly exceeds the bar answers (the exact context, the
 * two-decimal elapsed rendering, the claimed request identifier),
 * the boundary held exactly (the span that meets the bar is not
 * slow, the nanosecond over it is), the slow THROW recorded with
 * the throw itself propagating untouched (the one finally seam
 * both outcomes share), the disarmed family proven clock-less,
 * the broken clock seam that can fabricate nothing, and the
 * logging failure contained (a slow-but-successful request can
 * never become a failure through its own record).
 *
 * @internal
 */
#[CoversClass(SlowRequestMiddleware::class)]
#[CoversClass(SlowRequestRecord::class)]
final class SlowRequestMiddlewareTest extends TestCase
{
    private SteppingClock $steppingClock;

    private string $routedErrorLog = '';

    private false|string $previousErrorLog = false;

    #[Override]
    protected function setUp(): void
    {
        $this->steppingClock = new SteppingClock();
        $this->routedErrorLog = sys_get_temp_dir() . '/zef-slow-request-middleware-' . uniqid() . '.log';
        $this->previousErrorLog = (string) ini_set('error_log', $this->routedErrorLog);
    }

    #[Override]
    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousErrorLog);

        if (!is_file($this->routedErrorLog)) {
            return;
        }

        unlink($this->routedErrorLog);
    }

    public function testAFastRequestIsAnsweredWithoutARecord(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 500_000_000;

        $response = $this->middleware($recordingLogger)->process(
            $this->request(),
            $this->answeredHandler(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $recordingLogger->calls, "a fast request is nobody's record");
        self::assertSame(2, $this->steppingClock->readCount(), 'one span, two clock readings');
    }

    public function testASlowRequestAnswersTheRecord(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 1_500_000_000;

        $response = $this->middleware($recordingLogger)->process(
            $this->request(),
            $this->answeredHandler(),
        );

        self::assertSame(200, $response->getStatusCode(), 'the answer is untouched by the record');

        self::assertCount(1, $recordingLogger->calls);
        [$level, $message, $context] = $recordingLogger->calls[0];
        self::assertSame('warning', $level);
        self::assertSame('Slow request', $message);
        self::assertSame(
            [
                'request_id' => 'req-123',
                'method' => 'GET',
                'path' => '/health',
                'elapsed_ms' => 1500.0,
                'threshold_ms' => 1000,
            ],
            $context,
        );
    }

    public function testTheElapsedFactRendersAtTwoDecimals(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 1_500_435_000;

        $this->middleware($recordingLogger)->process($this->request(), $this->answeredHandler());

        self::assertSame(1500.44, $recordingLogger->calls[0][2]['elapsed_ms']);
    }

    public function testTheElapsedFactHoldsTheMillisecondDivisorExactly(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 2_000_004_900;

        $this->middleware($recordingLogger)->process($this->request(), $this->answeredHandler());

        self::assertSame(2000.0, $recordingLogger->calls[0][2]['elapsed_ms']);
    }

    public function testTheElapsedFactRoundsUpAcrossTheHalfMillisecond(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 2_000_005_100;

        $this->middleware($recordingLogger)->process($this->request(), $this->answeredHandler());

        self::assertSame(2000.01, $recordingLogger->calls[0][2]['elapsed_ms']);
    }

    public function testTheClockEpochDoesNotPolluteTheMeasuredSpan(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->epoch = 10_000_000_000;
        $this->steppingClock->step = 1_500_000_000;

        $this->middleware($recordingLogger)->process($this->request(), $this->answeredHandler());

        self::assertSame(1500.0, $recordingLogger->calls[0][2]['elapsed_ms']);
    }

    public function testThePathCapCountsCharactersNotBytes(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 1_500_000_000;

        $this->middleware($recordingLogger)->process(
            new ServerRequest('GET', new RawPathUri(str_repeat("\u{e4}", 3000))),
            $this->answeredHandler(),
        );

        $path = $recordingLogger->calls[0][2]['path'] ?? null;
        self::assertIsString($path);
        self::assertSame(
            4096,
            mb_strlen($path, '8bit'),
            'the cap counts characters: 2048 two-byte characters, not 2048 bytes',
        );
    }

    public function testTheRecordCarriesTheBarItWasJudgedAgainst(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 2_600_000_000;

        $this->middleware($recordingLogger, thresholdMs: 2500)
            ->process($this->request(), $this->answeredHandler())
        ;

        self::assertSame(2500, $recordingLogger->calls[0][2]['threshold_ms']);
        self::assertSame(2600.0, $recordingLogger->calls[0][2]['elapsed_ms']);
    }

    public function testASpanExactlyMeetingTheBarIsNotSlow(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 1_000_000_000;

        $this->middleware($recordingLogger)->process($this->request(), $this->answeredHandler());

        self::assertSame(
            [],
            $recordingLogger->calls,
            'melebihi is strictly exceeds: the span that exactly meets the bar is not slow',
        );
    }

    public function testASpanOneNanosecondOverTheBarRecords(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 1_000_000_001;

        $this->middleware($recordingLogger)->process($this->request(), $this->answeredHandler());

        self::assertCount(1, $recordingLogger->calls);
        self::assertSame(1000.0, $recordingLogger->calls[0][2]['elapsed_ms']);
    }

    public function testASlowThrowIsRecordedAndTheThrowPropagatesUntouched(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 1_500_000_000;

        try {
            $this->middleware($recordingLogger)->process(
                $this->request(),
                new ThrowingHandler(new RuntimeException('the handler failed')),
            );
            self::fail("The handler's throw must propagate through the boundary.");
        } catch (RuntimeException $runtimeException) {
            self::assertSame('the handler failed', $runtimeException->getMessage());
        }

        self::assertCount(1, $recordingLogger->calls, 'the throw path is the pathology the family most exists for');
        self::assertSame('Slow request', $recordingLogger->calls[0][1]);
        self::assertSame(1500.0, $recordingLogger->calls[0][2]['elapsed_ms']);
    }

    public function testAFastThrowIsNobodySRecord(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 10_000_000;

        try {
            $this->middleware($recordingLogger)->process(
                $this->request(),
                new ThrowingHandler(new RuntimeException('the handler failed')),
            );
            self::fail("The handler's throw must propagate through the boundary.");
        } catch (RuntimeException) {
        }

        self::assertSame([], $recordingLogger->calls, "a fast failure is the error lane's record, not this family's");
    }

    public function testADisarmedFamilyNeverReadsTheClock(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 1_500_000_000;

        $response = $this->middleware($recordingLogger, enabled: false)
            ->process($this->request(), $this->answeredHandler())
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $this->steppingClock->readCount(), 'the inert posture performs no work at all');
        self::assertSame([], $recordingLogger->calls);
    }

    public function testABrokenClockSeamFabricatesNothing(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = -1_500_000_000;

        $response = $this->middleware($recordingLogger)->process(
            $this->request(),
            $this->answeredHandler(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            [],
            $recordingLogger->calls,
            'a negative span no positive bar can exceed: a broken measurement can fabricate nothing',
        );
    }

    public function testALoggingFailureCannotConvertASlowAnswerIntoAFailure(): void
    {
        $this->steppingClock->step = 1_500_000_000;

        $response = $this->middleware(new FailingLogger(new RuntimeException('the sink is broken')))
            ->process($this->request(), $this->answeredHandler())
        ;

        self::assertSame(200, $response->getStatusCode(), 'the containment seam holds the failure');
        self::assertStringContainsString(
            'ZEF logging failure: RuntimeException',
            (string) file_get_contents($this->routedErrorLog),
            'the contained failure still answers its own diagnostic',
        );
    }

    public function testTheRecordCarriesAGeneratedRequestIdentifierWhenNoneWasClaimed(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 1_500_000_000;

        $this->middleware($recordingLogger)->process(
            new ServerRequest('GET', new Uri('https://app.example.com/health')),
            $this->answeredHandler(),
        );

        $requestId = $recordingLogger->calls[0][2]['request_id'] ?? null;
        self::assertIsString($requestId);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{32}$/D',
            $requestId,
            "an unclaimed request records under a fresh identifier, exactly as the error lane's records do",
        );
    }

    public function testThePathFactIsCappedAtTheErrorLaneSBound(): void
    {
        $recordingLogger = new RecordingLogger();
        $this->steppingClock->step = 1_500_000_000;

        $this->middleware($recordingLogger)->process(
            new ServerRequest('GET', new Uri('https://app.example.com/' . str_repeat('a', 3000))),
            $this->answeredHandler(),
        );

        $path = $recordingLogger->calls[0][2]['path'] ?? null;
        self::assertIsString($path);
        self::assertSame(
            2048,
            mb_strlen($path, '8bit'),
            "the request facts carry the error lane's own cap",
        );
    }

    /**
     * The family's middleware over the frozen clock.
     */
    private function middleware(
        ?LoggerInterface $logger,
        bool $enabled = true,
        int $thresholdMs = 1000,
    ): SlowRequestMiddleware {
        return new SlowRequestMiddleware(
            new SlowRequestPolicy(enabled: $enabled, thresholdMs: $thresholdMs),
            $logger,
            $this->steppingClock->now(),
        );
    }

    /**
     * The handler every fast path answers through.
     */
    private function answeredHandler(): FixedResponseHandler
    {
        return new FixedResponseHandler(new Response(200, [], 'answered'));
    }

    /**
     * The request every verdict judges: a claim the response echoes.
     */
    private function request(): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('https://app.example.com/health'),
            headers: ['X-Request-ID' => 'req-123'],
        );
    }
}
