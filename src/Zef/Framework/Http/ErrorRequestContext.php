<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Mutable, request-scoped correlation between the outer error boundary and
 * inner tracing middleware. The object is shared by reference through PSR-7
 * request attributes, never stored on a persistent worker service.
 *
 * The construction identifier is the boundary's own mint ({@see
 * CorrelationId}) carried immutably: the trace id may later be replaced by
 * a valid W3C one ({@see self::setTraceId()}), but the request's
 * correlation identifier -- the one the boundary stamps on the response
 * and consults for every record the request's own lanes write -- never
 * changes after the boundary mints it (#796: the slow-request record joins
 * the error lane's records on the identifier the response carries, and
 * the mutable trace id must never masquerade as it). The context is also
 * the route dimension's own carrier: the router's request handler writes
 * the matched route's pattern once at the match it already computed
 * ({@see self::setRoute()}), the recorder slicing the errors total by
 * the route the request actually landed on -- the same one-way write the
 * trace id's adoption rides, never a new seam.
 */
final class ErrorRequestContext
{
    public const string ATTRIBUTE = 'zef.error_context';

    private int $queryCount = 0;

    private int $exceptionCount = 0;

    private ?string $replayId = null;

    private ?string $route = null;

    private readonly int $startedAt;

    private readonly string $correlationId;

    public function __construct(private string $traceId)
    {
        $this->correlationId = $traceId;
        $this->startedAt = (int) hrtime(true);
    }

    /**
     * Narrows a raw request-attribute value to the context it may carry -- the
     * PSR-7 bag is untyped, so the boundary consumes it without ever assigning
     * the mixed raw value to a local.
     *
     * @param mixed $attribute the raw request attribute value
     */
    public static function fromAttribute(mixed $attribute): ?self
    {
        return $attribute instanceof self ? $attribute : null;
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

    /**
     * The boundary's own mint, carried immutably: the identifier the
     * response's `X-Request-ID` carries and every request-scoped record
     * ({@see CorrelationId::for()}) consults, never the mutable trace id
     * a valid W3C one may replace it with.
     */
    public function correlationId(): string
    {
        return $this->correlationId;
    }

    /**
     * The matched route's own pattern, written once by the router's
     * request handler at the match it already computed ({@see
     * \Zef\Framework\Middleware\RouterRequestHandler}) -- the same
     * inner-to-outer carrier the trace id rides, the boundary reading
     * what the deeper layer wrote on the object it minted. The value
     * is the ROUTING TABLE's own spelling (`/api/users/{id}`, the
     * pattern the route's grammar parsed), never the raw request
     * target: the errors total's route dimension is sliced by the
     * deployment's own route vocabulary, and a path that carries
     * captured values is a cardinality bomb and a privacy leak the
     * meter's low-cardinality doctrine and the log context's own
     * exclusions both refuse. The setter carries no grammar of its
     * own because the source owns one ({@see
     * \Zef\Framework\Config\Routing\RoutePath} parses and bounds
     * the pattern before it ever arrives here).
     */
    public function setRoute(string $route): void
    {
        $this->route = $route;
    }

    /**
     * The matched route's pattern, or null when no route matched (or
     * no boundary minted a context at all): the fold the errors
     * total's route dimension reads -- the closed spelling the
     * recorder answers for every unrouted refusal.
     */
    public function route(): ?string
    {
        return $this->route;
    }

    public function recordQuery(): void
    {
        $this->queryCount = min($this->queryCount + 1, 1_000_000);
    }

    /**
     * Lets an adapter increment the active request's counter without storing
     * mutable request state on a persistent-worker singleton.
     *
     * The null answer is the no-request-window truth (a console run, a
     * warm-up walk, a boot-time probe): it counts nothing and refuses
     * nothing -- the same honest posture the vendored PDO adapter's own
     * feeder folds through when its composition's provider answers that
     * no request is active.
     *
     * @api the documented host-adapter hook (`docs/error-handling.md`) --
     *      database adapters call this from their own code, so the framework
     *      tree alone cannot show the call.
     */
    public static function recordQueryFor(?ServerRequestInterface $serverRequest): void
    {
        $context = self::fromAttribute($serverRequest?->getAttribute(self::ATTRIBUTE));
        $context?->recordQuery();
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
        if (preg_match('/^[a-f0-9]{32}$/D', $replayId) !== 1) {
            return;
        }

        $this->replayId = $replayId;
    }

    public function replayId(): ?string
    {
        return $this->replayId;
    }
}
