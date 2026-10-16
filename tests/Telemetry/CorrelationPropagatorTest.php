<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\CorrelationContext;
use Zef\Framework\Telemetry\CorrelationHeaders;
use Zef\Framework\Telemetry\CorrelationIdentity;
use Zef\Framework\Telemetry\CorrelationInboundPair;
use Zef\Framework\Telemetry\CorrelationPropagator;

/**
 * The operation-level propagation pair, on its own terms: the
 * tolerant extraction's every refusal (the inbound reading's own
 * unit under it), the injected transport pair, the disabled null
 * -- the boundary remote and distributed execution correlates
 * through.
 *
 * @internal
 */
#[CoversClass(CorrelationPropagator::class)]
#[CoversClass(CorrelationInboundPair::class)]
final class CorrelationPropagatorTest extends TestCase
{
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string SPAN_ID = '00f067aa0ba902b7';

    private const string TRACE_PARENT = '00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01';

    public function testExtractsTheWellFormedPair(): void
    {
        $context = CorrelationPropagator::extract(
            self::TRACE_PARENT,
            'vendor=value',
            'orders.create',
            'idem-001',
            ['operation.class' => 'order', 'status' => 200],
        );

        self::assertInstanceOf(CorrelationContext::class, $context);
        self::assertSame(self::TRACE_ID, $context->identity->traceId);
        self::assertSame(self::SPAN_ID, $context->identity->spanId);
        self::assertSame('01', $context->identity->traceFlags);
        self::assertSame('vendor=value', $context->identity->traceState);
        self::assertSame('orders.create', $context->operationId);
        self::assertSame('idem-001', $context->idempotencyKey);
        self::assertSame(['operation.class' => 'order', 'status' => 200], $context->attributes);
    }

    public function testNormalisesUppercaseHexToLowercase(): void
    {
        $context = CorrelationPropagator::extract(
            '00-' . mb_strtoupper(self::TRACE_ID) . '-' . mb_strtoupper(self::SPAN_ID) . '-01',
            null,
            'orders.create',
        );

        self::assertInstanceOf(CorrelationContext::class, $context);
        self::assertSame(self::TRACE_ID, $context->identity->traceId);
        self::assertSame(self::SPAN_ID, $context->identity->spanId);
    }

    public function testWhitespacePaddingIsTheOversizeRefusal(): void
    {
        self::assertNull(CorrelationPropagator::extract(' ' . self::TRACE_PARENT . ' ', null, 'orders.create'));
    }

    public function testRejectsTheNullParent(): void
    {
        self::assertNull(CorrelationPropagator::extract(null, null, 'orders.create'));
    }

    public function testExtractsTheCompanionAtItsCeiling(): void
    {
        // Two members, not one: the spec's own value bound (256
        // characters per member) caps a single member under the
        // companion's 512-byte ceiling, so the ceiling is reached as
        // a list -- the way the wire reaches it.
        $atCeiling = 'k=' . str_repeat('a', 254) . ',k=' . str_repeat('a', 253);

        $context = CorrelationPropagator::extract(self::TRACE_PARENT, $atCeiling, 'orders.create');

        self::assertInstanceOf(CorrelationContext::class, $context);
        self::assertSame($atCeiling, $context->identity->traceState);
    }

    public function testRejectsTheOversizedParent(): void
    {
        self::assertNull(CorrelationPropagator::extract(
            self::TRACE_PARENT . '0',
            null,
            'orders.create',
        ));
    }

    public function testRejectsTheOversizedCompanion(): void
    {
        self::assertNull(CorrelationPropagator::extract(
            self::TRACE_PARENT,
            str_repeat('x', CorrelationIdentity::MAX_TRACESTATE_BYTES + 1),
            'orders.create',
        ));
    }

    public function testRejectsTheParentThatTrimsToTheWrongWidth(): void
    {
        $short = ' ' . mb_substr(self::TRACE_PARENT, 0, 54);

        self::assertNull(CorrelationPropagator::extract($short, null, 'orders.create'));
    }

    public function testRejectsTheMalformedParent(): void
    {
        self::assertNull(CorrelationPropagator::extract('invalid', null, 'orders.create'));
        self::assertNull(CorrelationPropagator::extract(
            '01-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01',
            null,
            'orders.create',
        ));
    }

    public function testRejectsThePairTheContextWouldRefuse(): void
    {
        self::assertNull(CorrelationPropagator::extract(
            '00-' . str_repeat('0', 32) . '-' . self::SPAN_ID . '-01',
            null,
            'orders.create',
        ), 'the all-zero trace id is a constructor refusal');
        self::assertNull(CorrelationPropagator::extract(
            self::TRACE_PARENT,
            null,
            '',
        ), 'the empty operation id is a constructor refusal');
    }

    public function testInjectsTheContextIntoTheTransportPair(): void
    {
        $correlationContext = new CorrelationContext(
            new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', 'vendor=value'),
            'orders.create',
        );

        $headers = CorrelationPropagator::inject($correlationContext);

        self::assertInstanceOf(CorrelationHeaders::class, $headers);
        self::assertSame(self::TRACE_PARENT, $headers->traceParent);
        self::assertSame('vendor=value', $headers->traceState);
    }

    public function testTheIdempotencyKeyNeverReachesTheWire(): void
    {
        $correlationContext = new CorrelationContext(
            new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', null),
            'orders.create',
            'idem-secret',
        );

        $headers = CorrelationPropagator::inject($correlationContext);

        self::assertInstanceOf(CorrelationHeaders::class, $headers);
        self::assertStringNotContainsString('idem-secret', $headers->traceParent);
        self::assertNull($headers->traceState);
    }

    public function testInjectsTheNullContextAsTheNullPair(): void
    {
        $absent = null;

        self::assertNull(CorrelationPropagator::inject($absent));
    }

    public function testTheDisabledPathAnswersNullWithoutAContextObject(): void
    {
        /** @var mixed $answer the disabled path's own literal null, read as the contract */
        $answer = CorrelationPropagator::disabled();

        self::assertNull($answer);
    }
}
