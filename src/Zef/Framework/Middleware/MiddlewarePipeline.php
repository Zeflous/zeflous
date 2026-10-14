<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Http\Response;

/**
 * The immutable PSR-15 execution pipeline.
 *
 * The design is the reference repository's `MiddlewarePipeline` ported
 * verbatim in behaviour (`ROADMAP.md`, "Middleware Pipeline" -> core
 * architecture): the class is `final readonly`, so after construction no
 * internal state (stack, terminal handler, index) can be mutated in place;
 * advancing the execution index happens by constructing the *next* stage as
 * a new instance (`new self(...)`), never by writing to `$index` -- the
 * clone-on-write discipline that keeps a persistent worker's in-flight
 * pipelines free of shared-state races.
 *
 * A pipeline always ends at one terminal handler (the controller/action the
 * dispatcher resolves). When a pipeline is built without one -- a
 * programming error the factory never produces -- the exhausted stack
 * answers a 500 plain-text response rather than failing silently, so a
 * response is produced even on the error path.
 */
final readonly class MiddlewarePipeline implements RequestHandlerInterface
{
    /**
     * @param list<MiddlewareInterface> $stack
     */
    public function __construct(
        private array $stack = [],
        private ?RequestHandlerInterface $requestHandler = null,
        private int $index = 0,
    ) {
    }

    /**
     * Returns a new pipeline with the middleware appended after the current
     * stack (the original is never modified).
     */
    public function withMiddleware(MiddlewareInterface $middleware): self
    {
        $stack = $this->stack;
        $stack[] = $middleware;

        return new self($stack, $this->requestHandler, $this->index);
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->index >= \count($this->stack)) {
            if (!$this->requestHandler instanceof RequestHandlerInterface) {
                return new Response(500, ['Content-Type' => 'text/plain'], 'Pipeline terminal missing.');
            }

            return $this->requestHandler->handle($request);
        }

        $next = new self($this->stack, $this->requestHandler, $this->index + 1);

        return $this->stack[$this->index]->process($request, $next);
    }
}
