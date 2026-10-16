<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;

/**
 * The bounded causal context: the diagnostic identity one remote or
 * distributed execution travels under -- the W3C identity ({@see
 * CorrelationIdentity}), the operation's own token, the idempotency
 * key when the caller gave one, and a bounded attribute table.
 *
 * Correlation is diagnostic and causal only; it is never an
 * authorization or a retry signal -- the context's own contract
 * (the reference's header comment, kept verbatim in spirit): a
 * caller that trusts a propagated identity for access control has
 * moved the boundary to the one channel that was never guarded,
 * and a caller that replays an idempotency key because the wire
 * repeated it has confused the wire's retransmission with the
 * application's retry semantics.
 *
 * The constructor is the validation: the identity's own W3C grammars
 * and wire ceiling, the printable bounded tokens for the operation
 * and idempotency identities, the {@see CorrelationAttributes}
 * census for the table. Everything downstream -- the {@see
 * CorrelationHeaders} render, the propagation pair's injection --
 * receives a context that already satisfies every bound the wire
 * will enforce.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Observability/CorrelationContext.php`): the token
 * grammars and the attribute census move verbatim; the port's
 * deviations are the decomposition -- the W3C identity fields, their
 * grammars and the wire ceiling live in the identity's own value
 * object ({@see CorrelationIdentity}), the attribute table's
 * validation in the table's own unit ({@see CorrelationAttributes})
 * with its budget ({@see CorrelationBudget}) and its redaction
 * ({@see CorrelationRedaction}), this context composing them
 * rather than carrying every grammar in one class -- and the
 * dropped empty-token guard, which the port's own mutation
 * campaign proved unreachable-in-effect: the printable grammar
 * refuses the empty token with the same refusal.
 */
final readonly class CorrelationContext
{
    /** The operation identity's ceiling. */
    public const int MAX_OPERATION_ID_BYTES = 128;

    /** The idempotency identity's ceiling. */
    public const int MAX_IDEMPOTENCY_KEY_BYTES = 128;

    /** @var array<string, null|bool|float|int|string> */
    public array $attributes;

    /**
     * @param string                                    $operationId    the execution's operation identity
     * @param null|string                               $idempotencyKey the caller's idempotency identity
     * @param array<string, null|bool|float|int|string> $attributes     the bounded diagnostic table
     *
     * @throws InvalidArgumentException when a token fails its grammar, the table
     *                                  fails its census, or the identity fails
     *                                  the W3C grammar or the wire ceiling
     */
    public function __construct(
        public CorrelationIdentity $identity,
        public string $operationId,
        public ?string $idempotencyKey = null,
        array $attributes = [],
    ) {
        $this->validateToken($operationId, self::MAX_OPERATION_ID_BYTES, 'operationId');
        $this->validateOptionalToken($idempotencyKey);
        $this->attributes = CorrelationAttributes::validated($attributes);
    }

    /**
     * The table's redacted view: correlation-safe diagnostics only,
     * the sensitive values as stable sha256 digests ({@see
     * CorrelationRedaction::redacted()}).
     *
     * @return array<string, null|bool|float|int|string>
     */
    public function redactedAttributes(): array
    {
        return CorrelationRedaction::redacted($this->attributes);
    }

    /**
     * @throws InvalidArgumentException when the token is empty, oversized or not printable ASCII without separators
     */
    private function validateToken(string $value, int $max, string $name): void
    {
        if (mb_strlen($value, '8bit') > $max) {
            throw new InvalidArgumentException(\sprintf('%s must be a bounded printable token.', $name));
        }

        if (preg_match('/^[\x21-\x7E]+$/', $value) !== 1) {
            throw new InvalidArgumentException(\sprintf('%s must be a bounded printable token.', $name));
        }
    }

    /**
     * The optional identity's guard: a null idempotency key is the
     * caller's "no key" and validates as itself.
     *
     * @throws InvalidArgumentException when the present key fails the token grammar
     */
    private function validateOptionalToken(?string $idempotencyKey): void
    {
        if ($idempotencyKey === null) {
            return;
        }

        $this->validateToken($idempotencyKey, self::MAX_IDEMPOTENCY_KEY_BYTES, 'idempotencyKey');
    }
}
