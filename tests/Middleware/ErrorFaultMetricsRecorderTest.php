<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\ErrorFaultMetricsRecorder;
use Zef\Test\Support\Telemetry\RecordingMeter;

/**
 * Pins the fault metric's route dimension at its own seam: the recorder
 * reads the pattern the error boundary's context carries -- the routed
 * context's own spelling for the dimension, the closed unrouted word for
 * the context that carries no route, and the same closed word for the
 * request that carries no context at all (a custom stack that never
 * minted one) -- the increment itself standing as the containment proof
 * (a dimension fold that refuses answers nothing but the word).
 *
 * @internal
 */
#[CoversClass(ErrorFaultMetricsRecorder::class)]
final class ErrorFaultMetricsRecorderTest extends TestCase
{
    public function testTheRoutedContextsPatternAnswersTheRouteDimension(): void
    {
        $recordingMeter = new RecordingMeter();
        $errorRequestContext = new ErrorRequestContext('request-123');
        $errorRequestContext->setRoute('/api/users/{id}');
        $serverRequest = $this->requestCarrying($errorRequestContext);

        ErrorFaultMetricsRecorder::record(
            $recordingMeter,
            new RuntimeException('application failure'),
            500,
            $serverRequest,
        );

        self::assertCount(1, $recordingMeter->increments);
        self::assertSame('/api/users/{id}', $recordingMeter->increments[0]['attributes']['http.route']);
        self::assertSame(500, $recordingMeter->increments[0]['attributes']['http.response.status_code']);
        self::assertSame('RuntimeException', $recordingMeter->increments[0]['attributes']['exception.type']);
    }

    public function testTheRoutelessContextAnswersTheClosedUnroutedWord(): void
    {
        $recordingMeter = new RecordingMeter();
        $serverRequest = $this->requestCarrying(new ErrorRequestContext('request-123'));

        ErrorFaultMetricsRecorder::record(
            $recordingMeter,
            new RuntimeException('application failure'),
            500,
            $serverRequest,
        );

        self::assertCount(1, $recordingMeter->increments);
        self::assertSame('unrouted', $recordingMeter->increments[0]['attributes']['http.route']);
    }

    public function testTheContextlessRequestAnswersTheClosedUnroutedWord(): void
    {
        $recordingMeter = new RecordingMeter();
        $contextlessRequest = new ServerRequest('GET', new Uri('https://app.example/orders/92'));

        ErrorFaultMetricsRecorder::record(
            $recordingMeter,
            new RuntimeException('application failure'),
            500,
            $contextlessRequest,
        );

        self::assertCount(1, $recordingMeter->increments);
        self::assertSame(
            'unrouted',
            $recordingMeter->increments[0]['attributes']['http.route'],
            'a request no boundary minted a context onto answers the closed word, and the increment stands',
        );
    }

    private function requestCarrying(ErrorRequestContext $errorRequestContext): ServerRequestInterface
    {
        $serverRequest = new ServerRequest('GET', new Uri('https://app.example/api/users/92'));

        return $serverRequest->withAttribute(ErrorRequestContext::ATTRIBUTE, $errorRequestContext);
    }
}
