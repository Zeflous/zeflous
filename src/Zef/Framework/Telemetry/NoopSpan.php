<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use Override;

/**
 * The void span: the disabled tracer's one answer -- a span that is
 * already ended, carries the invalid context ({@see SpanContext::
 * invalid()}), and accepts every recording call as a no-op.
 *
 * The singleton ({@see self::instance()}) is the cost shape, not a
 * state sharing: the span has no state to share -- every mutator
 * returns `$this` unchanged, the end is a no-op, the identity is the
 * void one -- so one instance serves every disabled call the
 * process will ever make, and the disabled family's per-span cost
 * is the singleton read, nothing else.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Application/Observability/NoopSpan.php`) verbatim in behaviour:
 * the ended answer is `true` (a void span was never measuring), and
 * the invalid context is the only identity it can honestly carry.
 */
final class NoopSpan implements SpanInterface
{
    private static ?self $instance = null;

    /**
     * The void span the disabled tracer answers with.
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    #[Override]
    public function getContext(): SpanContext
    {
        return SpanContext::invalid();
    }

    #[Override]
    public function setAttribute(string $key, mixed $value): self
    {
        return $this;
    }

    #[Override]
    public function setAttributes(array $attributes): self
    {
        return $this;
    }

    #[Override]
    public function addEvent(string $name, array $attributes = []): self
    {
        return $this;
    }

    #[Override]
    public function setStatus(string $status, ?string $description = null): self
    {
        return $this;
    }

    #[Override]
    public function end(?int $endNs = null): void
    {
        // The void span was never measuring: there is no timeline to
        // close and no record to hand over.
    }

    #[Override]
    public function isEnded(): bool
    {
        return true;
    }
}
