<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Mutable, request-scoped correlation between the outer error boundary and
 * inner tracing middleware. The object is shared by reference through PSR-7
 * request attributes, never stored on a persistent worker service.
 */
final class ErrorRequestContext
{
    public const string ATTRIBUTE = 'zef.error_context';

    private string $traceId;

    private int $queryCount = 0;

    private int $exceptionCount = 0;

    private ?string $replayId = null;

    private readonly int $startedAt;

    public function __construct(string $requestId)
    {
        $this->traceId = $requestId;
        $this->startedAt = (int) hrtime(true);
    }

    /** Only a valid non-zero W3C trace ID can replace the request-ID fallback. */
    public function setTraceId(string $traceId): void
    {
        if (preg_match('/^(?!0{32})[a-f0-9]{32}$/iD', $traceId) !== 1) {
            return;
        }

        $this->traceId = mb_strtolower($traceId);
    }

    public function traceId(): string
    {
        return $this->traceId;
    }

    public function recordQuery(): void
    {
        $this->queryCount = min($this->queryCount + 1, 1_000_000);
    }

    /**
     * Lets an adapter increment the active request's counter without storing
     * mutable request state on a persistent-worker singleton.
     */
    public static function recordQueryFor(ServerRequestInterface $request): void
    {
        $context = $request->getAttribute(self::ATTRIBUTE);
        if ($context instanceof self) {
            $context->recordQuery();
        }
    }

    public function queryCount(): int
    {
        return $this->queryCount;
    }

    public function recordException(): void
    {
        $this->exceptionCount = min($this->exceptionCount + 1, 100);
    }

    public function exceptionCount(): int
    {
        return $this->exceptionCount;
    }

    public function durationMilliseconds(): float
    {
        return (hrtime(true) - $this->startedAt) / 1_000_000;
    }

    public function setReplayId(string $replayId): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $replayId) === 1) {
            $this->replayId = $replayId;
        }
    }

    public function replayId(): ?string
    {
        return $this->replayId;
    }
}
