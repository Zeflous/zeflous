<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\OtlpExporterException;
use Zef\Framework\Telemetry\OtlpHttpJsonExporter;
use Zef\Framework\Telemetry\OtlpHttpTransport;
use Zef\Framework\Telemetry\OtlpJsonPayloadEncoder;
use Zef\Framework\Telemetry\SpanContext;
use Zef\Framework\Telemetry\SpanData;
use Zef\Test\Support\Telemetry\OtlpMockServer;

/**
 * The OTLP/HTTP-JSON span exporter, on its own terms and over the
 * real transport: the timeout's own bounds, the empty batch's
 * no-op, the payload's exact wire shape (resource, scope, span,
 * parent, status, events -- captured by a real loopback collector
 * the exporter's own stream context delivers to), the answer's
 * triage (the 2xx delivery, the 4xx permanent rejection, the 5xx
 * transient rejection), and the refused transport (a closed port,
 * whose own diagnostics ride the failure redacted).
 *
 * The loopback collector is the engine's own built-in development
 * server over an ephemeral port ({@see OtlpMockServer}): the same
 * real-path discipline the error-mapping family's end-to-end test
 * carries -- the status line the triage reads is the engine's own
 * `$http_response_header`, which only a real HTTP answer
 * populates, so every branch of the mapping is pinned against the
 * branch the wire itself exercises.
 *
 * @internal
 */
#[CoversClass(OtlpHttpJsonExporter::class)]
#[CoversClass(OtlpHttpTransport::class)]
#[CoversClass(OtlpJsonPayloadEncoder::class)]
final class OtlpHttpJsonExporterTest extends TestCase
{
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string SPAN_ID = '00f067aa0ba902b7';

    private const string PARENT_SPAN_ID = 'b7ad6b7169203331';

    private OtlpMockServer $otlpMockServer;

    #[Override]
    protected function setUp(): void
    {
        $this->otlpMockServer = new OtlpMockServer();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->otlpMockServer->stop();
    }

    public function testTheTimeoutsBoundsAreRefused(): void
    {
        try {
            new OtlpHttpJsonExporter('http://localhost:4318', [], 0);
            self::fail('A zero timeout must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'timeoutMs is outside its allowed range.',
                $invalidArgumentException->getMessage(),
            );
        }

        try {
            new OtlpHttpJsonExporter('http://localhost:4318', [], 10001);
            self::fail('An over-bound timeout must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'timeoutMs is outside its allowed range.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheBoundsAreInclusive(): void
    {
        $low = new OtlpHttpJsonExporter('http://localhost:4318', [], 1);
        $high = new OtlpHttpJsonExporter('http://localhost:4318', [], 10000);

        self::assertNotSame($low, $high, 'both bounds construct -- two live exporters, not a shared instance');
    }

    public function testAnEmptyBatchIsANoOpWithoutATransport(): void
    {
        // The closed port would fail the transport in milliseconds:
        // an empty batch never reaches it.
        $otlpHttpJsonExporter = new OtlpHttpJsonExporter('http://127.0.0.1:1', [], 50);

        $otlpHttpJsonExporter->export([]);

        self::addToAssertionCount(1);
    }

    public function testARefusedTransportFailsWithTheRedactedReason(): void
    {
        $otlpHttpJsonExporter = new OtlpHttpJsonExporter('http://127.0.0.1:1', ['service.name' => 'svc'], 50);

        try {
            $otlpHttpJsonExporter->export([$this->record('refused')]);
            self::fail('A refused connection must fail the export.');
        } catch (OtlpExporterException $otlpExporterException) {
            self::assertStringStartsWith(
                'OTLP exporter transport failure',
                $otlpExporterException->getMessage(),
            );
            self::assertStringEndsWith('.', $otlpExporterException->getMessage());
        }
    }

    public function testTheTransportFailureDiagnosticsAreRedacted(): void
    {
        // The refused transport's own warning carries the full request
        // URL the engine built, query string included -- a
        // credential-bearing parameter would ride the diagnostic out
        // of the process through the exception text, so the failure's
        // reason must arrive redacted: the closed port answers fast,
        // and the pair vocabulary scrubs the token out of the message
        // that rides the lane's own exception. The URL's own shape is
        // pinned too -- the signal path folded into the path, the
        // query kept after it -- because a fold that addressed the
        // query as part of the path would still redact the token while
        // addressing the wrong resource.
        $otlpHttpJsonExporter = new OtlpHttpJsonExporter('http://127.0.0.1:1/?token=secret-value', [], 50);

        try {
            $otlpHttpJsonExporter->export([$this->record('redacted')]);
            self::fail('The refused connection must fail the export.');
        } catch (OtlpExporterException $otlpExporterException) {
            self::assertStringContainsString('transport failure', $otlpExporterException->getMessage());
            self::assertStringContainsString(
                'http://127.0.0.1:1/v1/traces?token=[REDACTED]',
                $otlpExporterException->getMessage(),
                'the query rides after the signal path, its token scrubbed',
            );
            self::assertStringNotContainsString('secret-value', $otlpExporterException->getMessage());
        }
    }

    public function testADeliveredBatchCarriesTheWirePayload(): void
    {
        $endpoint = $this->otlpMockServer->start();
        $otlpHttpJsonExporter = new OtlpHttpJsonExporter(
            $endpoint,
            ['service.name' => 'zef-test-service'],
            500,
        );

        $otlpHttpJsonExporter->export([$this->record('the request', withParent: true, withEvents: true)]);

        $bodies = $this->otlpMockServer->receivedBodies();
        self::assertCount(1, $bodies, 'one delivery, one request');
        self::assertSame(1, $this->otlpMockServer->requests());

        $payload = $this->payloadAt($bodies[0]);
        $resourceSpan = $this->mapAt($this->mapAt($payload['resourceSpans'])[0]);
        self::assertSame(
            ['service.name' => 'zef-test-service'],
            $this->attributeMap($this->mapAt($this->mapAt($resourceSpan['resource'])['attributes'])),
            'the resource block carries the declared identity',
        );

        $scopeSpan = $this->mapAt($this->mapAt($resourceSpan['scopeSpans'])[0]);
        $scope = $this->mapAt($scopeSpan['scope']);
        self::assertSame('zef-observability', $scope['name']);

        $span = $this->mapAt($this->mapAt($scopeSpan['spans'])[0]);
        self::assertSame(self::TRACE_ID, $span['traceId']);
        self::assertSame(self::SPAN_ID, $span['spanId']);
        self::assertSame('the request', $span['name']);
        self::assertSame('SPAN_KIND_SERVER', $span['kind']);
        self::assertSame('3', $span['startTimeUnixNano']);
        self::assertSame('4', $span['endTimeUnixNano']);
        self::assertSame('STATUS_CODE_ERROR', $this->mapAt($span['status'])['code']);
        self::assertSame('upstream 503', $this->mapAt($span['status'])['message']);
        self::assertSame(self::PARENT_SPAN_ID, $span['parentSpanId']);
        self::assertSame(
            ['http.method' => 'GET'],
            $this->attributeMap($this->mapAt($span['attributes'])),
        );

        $event = $this->mapAt($this->mapAt($span['events'])[0]);
        self::assertSame('cache.miss', $event['name']);
        self::assertSame('5', $event['timeUnixNano']);
        self::assertSame(
            ['attempts' => '2'],
            $this->attributeMap($this->mapAt($event['attributes'])),
            'the integer attribute answers the string the protocol spells',
        );
    }

    public function testTheSignalPathIsAppendedOnce(): void
    {
        $endpoint = $this->otlpMockServer->start();
        $otlpHttpJsonExporter = new OtlpHttpJsonExporter($endpoint . '/', ['service.name' => 'svc'], 500);

        $otlpHttpJsonExporter->export([$this->record('appended')]);

        self::assertSame(1, $this->otlpMockServer->requests(), 'the trailing slash folds and the path lands once');
    }

    public function testAnOkStatusAnswersTheProtocolsOkCode(): void
    {
        $endpoint = $this->otlpMockServer->start();
        $otlpHttpJsonExporter = new OtlpHttpJsonExporter($endpoint, ['service.name' => 'svc'], 500);

        $otlpHttpJsonExporter->export([$this->record('answered', status: 'OK')]);

        $bodies = $this->otlpMockServer->receivedBodies();
        self::assertCount(1, $bodies);

        $payload = $this->payloadAt($bodies[0]);
        $resourceSpan = $this->mapAt($this->mapAt($payload['resourceSpans'])[0]);
        $scopeSpan = $this->mapAt($this->mapAt($resourceSpan['scopeSpans'])[0]);
        $span = $this->mapAt($this->mapAt($scopeSpan['spans'])[0]);

        self::assertSame(
            'STATUS_CODE_OK',
            $this->mapAt($span['status'])['code'],
            'the ok status answers the protocols own ok code',
        );
    }

    public function testAPermanentRejectionIsTheInvalidArgumentException(): void
    {
        $this->otlpMockServer = new OtlpMockServer(status: 400);
        $endpoint = $this->otlpMockServer->start();
        $otlpHttpJsonExporter = new OtlpHttpJsonExporter($endpoint, ['service.name' => 'svc'], 500);

        try {
            $otlpHttpJsonExporter->export([$this->record('rejected')]);
            self::fail('A 4xx answer must be the permanent rejection.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'OTLP exporter permanent rejection.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testATransientRejectionIsTheLanesOwnException(): void
    {
        $this->otlpMockServer = new OtlpMockServer(status: 503);
        $endpoint = $this->otlpMockServer->start();
        $otlpHttpJsonExporter = new OtlpHttpJsonExporter($endpoint, ['service.name' => 'svc'], 500);

        try {
            $otlpHttpJsonExporter->export([$this->record('unavailable')]);
            self::fail('A 5xx answer must be the transient rejection.');
        } catch (OtlpExporterException $otlpExporterException) {
            self::assertSame(
                'OTLP exporter transient rejection.',
                $otlpExporterException->getMessage(),
            );
        }
    }

    public function testASlowCollectorTimesTheExchangeOut(): void
    {
        $this->otlpMockServer = new OtlpMockServer(delayMs: 400);
        $endpoint = $this->otlpMockServer->start();
        $otlpHttpJsonExporter = new OtlpHttpJsonExporter($endpoint, ['service.name' => 'svc'], 10);

        $this->expectException(OtlpExporterException::class);

        $otlpHttpJsonExporter->export([$this->record('slow')]);
    }

    public function testTheShutdownIsIdempotentByConstruction(): void
    {
        $otlpHttpJsonExporter = new OtlpHttpJsonExporter('http://127.0.0.1:1', [], 50);

        $otlpHttpJsonExporter->shutdown();
        $otlpHttpJsonExporter->shutdown();

        self::addToAssertionCount(1);
    }

    /**
     * One completed-span record, with the optional arms the payload
     * shape needs: the parent context, the events.
     */
    private function record(
        string $name,
        bool $withParent = false,
        bool $withEvents = false,
        string $status = 'ERROR',
    ): SpanData {
        return new SpanData(
            $name,
            new SpanContext(self::TRACE_ID, self::SPAN_ID),
            $withParent ? new SpanContext(self::TRACE_ID, self::PARENT_SPAN_ID) : null,
            1,
            2,
            3,
            4,
            $status,
            'upstream 503',
            ['http.method' => 'GET'],
            $withEvents ? [
                ['name' => 'cache.miss', 'time_unix_nano' => 5, 'attributes' => ['attempts' => 2]],
            ] : [],
        );
    }

    /**
     * The delivered body, decoded and shaped: the top map the wire
     * payload must decode to -- the error-envelope discipline the
     * HTTP family's own suite established, spelled for the OTLP
     * wire shape.
     *
     * @return array<array-key, mixed>
     */
    private function payloadAt(string $body): array
    {
        $decoded = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            self::fail('The delivered payload did not decode to an object.');
        }

        return $decoded;
    }

    /**
     * One payload node the assertions read containers through,
     * shaped to the map it must be before any offset touches it.
     *
     * @return array<array-key, mixed>
     */
    private function mapAt(mixed $node): array
    {
        if (!\is_array($node)) {
            self::fail('The payload node the assertion read is not an object.');
        }

        return $node;
    }

    /**
     * Decodes the KeyValue list back into the map the assertions
     * read -- the wire's own shape, folded for comparison.
     *
     * @param array<array-key, mixed> $encoded
     *
     * @return array<string, mixed>
     */
    private function attributeMap(array $encoded): array
    {
        $map = [];

        foreach ($encoded as $entry) {
            $pair = $this->mapAt($entry);
            $key = $pair['key'];

            if (!\is_string($key)) {
                self::fail('The attribute key is not the string the protocol spells.');
            }

            $value = $this->mapAt($pair['value']);
            $carrier = array_key_first($value);

            if ($carrier === null) {
                self::fail('The attribute value carried no arm at all.');
            }

            $map[$key] = $value[$carrier];
        }

        return $map;
    }
}
