<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Zef\Framework\Http\CorrelationId;

/**
 * The boundary's request-facts memo: the one request the records'
 * arms observe, read once and held.
 *
 * The process boundary is installed around the process, and the
 * default request source reads the SAPI superglobals -- the classic
 * model where one process answers one request, so one read IS the
 * request's whole window. Reading it once and holding the derived
 * correlation identifier keeps every record of the window on the
 * same id: the per-record mint the lane carried before fragmented
 * one request's diagnostics into unrelated identifiers whenever the
 * request claimed no `X-Request-ID` (the majority of traffic), and
 * rebuilt the full server request per diagnostic besides.
 *
 * A persistent worker that keeps one boundary across requests resets
 * the window ({@see ProcessBoundaryRecords::freshRequest()}) at each
 * request's start -- or supplies its own request source that already
 * answers the current request, the same seam discipline the boundary
 * constructor documents.
 */
final class RequestFactsMemo
{
    private ?ServerRequestInterface $serverRequest = null;

    private string $correlation = '';

    /**
     * The memoised facts: the first call reads the source, every later
     * call answers the same request and the same correlation
     * identifier until the window is reset.
     *
     * @param Closure(): ServerRequestInterface $requestSource the source the window reads once
     *
     * @return array{request: ServerRequestInterface, correlation: string}
     */
    public function facts(Closure $requestSource): array
    {
        if (!$this->serverRequest instanceof ServerRequestInterface) {
            $this->serverRequest = ($requestSource)();
            $this->correlation = CorrelationId::for($this->serverRequest);
        }

        return ['request' => $this->serverRequest, 'correlation' => $this->correlation];
    }

    /**
     * Resets the window: the next facts read observes the request the
     * source answers then, not the one the process started with.
     */
    public function forget(): void
    {
        $this->serverRequest = null;
        $this->correlation = '';
    }
}
