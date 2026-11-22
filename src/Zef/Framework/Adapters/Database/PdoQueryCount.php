<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zef\Framework\Http\ErrorRequestContext;

/**
 * The request-scoped query counter's feeder: the fold the vendored
 * adapter's statement runs call once per executed statement, the call
 * `docs/error-handling.md` documented as the host-adapter hook
 * (`{@see ErrorRequestContext::recordQueryFor()}`) answered from the
 * adapter's own tree -- the toolbar's query counting waited on exactly
 * this call (#905's narrowed phrase: the hook and the display were
 * live, the PDO feeder alone missing).
 *
 * The counter the feeder increments is the request's own
 * {@see ErrorRequestContext} -- the mutable object the outer error
 * boundary mints and decorates onto the request, shared by reference
 * so the inner layers the request reaches can write back through it
 * (the trace-id adoption's own carrier). The feeder reads it the way
 * the hook spells: ask the request for the attribute, increment when
 * the boundary minted one, count nothing when it did not -- a
 * connection the CLI, the migrator or the boot's warm-up holds
 * answers no request at all, and a request that carries no boundary
 * context answers no counter to feed.
 *
 * THE REQUEST REACHES THE ADAPTER THROUGH THE COMPOSITION'S OWN ACT
 * -- a provider closure, `null|(Closure(): ?ServerRequestInterface)`,
 * handed the wiring beside the meter and the audit logger the
 * family's other optional seams ride. The framework itself mints no
 * holder of the active request (the {@see ErrorRequestContext}
 * doctrine: mutable request state never lives on a persistent-worker
 * service, and the connection is one), so the honest composition
 * spellings are exactly two: the deployment's own composition root
 * supplies the provider its runtime already owns (the persistent
 * worker's request context -- the meter's own process-wide seams were
 * the same composition act), or the composition spells no provider
 * and the family's counter stays the documented host hook. A provider
 * that answers null feeds nothing -- the no-active-request truth of a
 * console run or a warm-up walk, never an error.
 *
 * The feed is best-effort the whole query-log family already is: a
 * provider that throws, or one that answers a value no request
 * grammar recognises, can neither replace the statement's own answer
 * nor mask the exception a failing statement already threw -- the
 * observation is never worth the query (the closing edge's own
 * doctrine, the catch the containment).
 *
 * The counter rides the SAME finally seam the measured window's
 * closing edge rides: a statement that answered feeds the request's
 * counter, and a statement that threw feeds it and then propagates
 * its throw untouched -- a query the request paid is a query the
 * toolbar counts, whichever way the statement ended. A healed
 * statement (the lost-connection fold's re-materialised socket)
 * answers one window per attempt -- the per-attempt truth the
 * measured family's own windows answer.
 *
 * @internal consumed through {@see PdoConnection}'s statement verbs
 */
final readonly class PdoQueryCount
{
    /**
     * @param Closure(): ?ServerRequestInterface $requestProvider the composition's request seam
     */
    public function __construct(private Closure $requestProvider)
    {
    }

    /**
     * The counter the composition arms: the provider it carries, or
     * null when the composition spelled no provider -- the honest
     * void, the family's own null posture (no provider held, no
     * question asked, no cost paid at any statement run).
     *
     * @param null|Closure(): ?ServerRequestInterface $requestProvider the composition's request seam
     *
     * @return null|self the feeder the connection holds, or null when the composition carries none
     */
    public static function whenCarried(?Closure $requestProvider): ?self
    {
        return $requestProvider instanceof \Closure ? new self($requestProvider) : null;
    }

    /**
     * Feeds the active request's counter once: one executed statement,
     * one increment -- the toolbar's own count, read by the debug
     * toolbar and the debug error pages off the request's context.
     */
    public function feed(): void
    {
        try {
            ErrorRequestContext::recordQueryFor(($this->requestProvider)());
        } catch (Throwable) {
            // Best-effort: the query's own answer is never worth replacing.
        }
    }
}
