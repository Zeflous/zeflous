<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\CorrelationContext;
use Zef\Framework\Telemetry\CorrelationIdentity;

/**
 * The bounded causal context, on its own terms: the composition of
 * the W3C identity ({@see CorrelationIdentity}, proven in its own
 * test), the bounded operation and idempotency tokens, and the
 * attribute table's census -- plus the table's redacted diagnostic
 * view, the context's own answer.
 *
 * @internal
 */
#[CoversClass(CorrelationContext::class)]
final class CorrelationContextTest extends TestCase
{
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string SPAN_ID = '00f067aa0ba902b7';

    private const string TRACE_PARENT = '00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01';

    public function testComposesTheIdentityItWasGiven(): void
    {
        $correlationIdentity = new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', 'vendor=alpha');

        $correlationContext = new CorrelationContext($correlationIdentity, 'orders.create');

        self::assertSame($correlationIdentity, $correlationContext->identity);
        self::assertSame(self::TRACE_PARENT, $correlationContext->identity->traceParent());
        self::assertSame('vendor=alpha', $correlationContext->identity->traceState);
        self::assertSame('orders.create', $correlationContext->operationId);
        self::assertNull($correlationContext->idempotencyKey);
        self::assertSame([], $correlationContext->attributes);
    }

    public function testCarriesTheIdempotencyKeyAndTheTable(): void
    {
        $correlationContext = new CorrelationContext(
            new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', null),
            'orders.create',
            'idem-001',
            ['operation.class' => 'order', 'status' => 200],
        );

        self::assertSame('idem-001', $correlationContext->idempotencyKey);
        self::assertSame(['operation.class' => 'order', 'status' => 200], $correlationContext->attributes);
    }

    #[DataProvider('provideRejectsTheOperationIdThatFailsTheTokenGrammarCases')]
    public function testRejectsTheOperationIdThatFailsTheTokenGrammar(string $operationId): void
    {
        $this->assertRejected(
            static fn (): CorrelationContext => new CorrelationContext(
                new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', null),
                $operationId,
            ),
            'the operation token grammar',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRejectsTheOperationIdThatFailsTheTokenGrammarCases(): iterable
    {
        yield 'empty' => [''];

        yield 'oversized' => [str_repeat('a', CorrelationContext::MAX_OPERATION_ID_BYTES + 1)];

        yield 'a newline' => ["has\nnewline"];

        yield 'a space' => ['has space'];
    }

    public function testRejectsTheIdempotencyKeyThatFailsTheTokenGrammar(): void
    {
        $this->assertRejected(
            static fn (): CorrelationContext => new CorrelationContext(
                new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', null),
                'orders.create',
                str_repeat('b', CorrelationContext::MAX_IDEMPOTENCY_KEY_BYTES + 1),
            ),
            'the idempotency token grammar',
        );
    }

    public function testTheIdempotencyKeyIsOptional(): void
    {
        $correlationContext = new CorrelationContext(
            new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', null),
            'orders.create',
        );

        self::assertNull($correlationContext->idempotencyKey);
    }

    public function testTheOperationTokenAtItsOwnCeilingShips(): void
    {
        $operationId = str_repeat('o', CorrelationContext::MAX_OPERATION_ID_BYTES);

        $correlationContext = new CorrelationContext(
            new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', null),
            $operationId,
        );

        self::assertSame($operationId, $correlationContext->operationId);
    }

    public function testTheIdempotencyTokenAtItsOwnCeilingShips(): void
    {
        $idempotencyKey = str_repeat('i', CorrelationContext::MAX_IDEMPOTENCY_KEY_BYTES);

        $correlationContext = new CorrelationContext(
            new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', null),
            'orders.create',
            $idempotencyKey,
        );

        self::assertSame($idempotencyKey, $correlationContext->idempotencyKey);
    }

    public function testRejectsTheTableThatFailsTheCensus(): void
    {
        $this->assertRejected(
            static fn (): CorrelationContext => new CorrelationContext(
                new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', null),
                'orders.create',
                null,
                ['has space' => 'v'],
            ),
            'the attribute census',
        );
    }

    public function testRedactedAttributesAnswersTheDiagnosticView(): void
    {
        $correlationContext = new CorrelationContext(
            new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', null),
            'orders.create',
            'secret-idem',
            [
                'authorization.token' => 'secret-token',
                'operation.class' => 'order',
            ],
        );

        $redacted = $correlationContext->redactedAttributes();

        self::assertSame('order', $redacted['operation.class']);
        self::assertSame('sha256:' . hash('sha256', 'secret-token'), $redacted['authorization.token']);
        self::assertSame(
            ['authorization.token' => 'sha256:' . hash('sha256', 'secret-token'), 'operation.class' => 'order'],
            $redacted,
            "the redaction preserves the table's own order",
        );
    }

    public function testTheRedactionLeavesTheTableItCarriesUntouched(): void
    {
        $attributes = ['authorization.token' => 'secret-token', 'operation.class' => 'order'];

        $correlationContext = new CorrelationContext(
            new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', null),
            'orders.create',
            null,
            $attributes,
        );

        $correlationContext->redactedAttributes();

        self::assertSame($attributes, $correlationContext->attributes, 'the redacted view is a copy, never a mutation');
    }

    /**
     * @param callable(): CorrelationContext $construction
     */
    private function assertRejected(callable $construction, string $because): void
    {
        try {
            $construction();
            self::fail("A context that fails {$because} must be rejected.");
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
    }
}
