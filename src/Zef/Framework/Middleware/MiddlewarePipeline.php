<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Exception\InvariantViolationException;

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
 * raises the typed invariant signal ({@see InvariantViolationException})
 * instead of hand-building an error response: every error shape belongs to
 * the error boundary (CONTRIBUTING.md, rule 17), so the refusal travels the
 * same problem-details lane the boundary renders for every other fault --
 * through the mapping middleware when one wraps the stack, through the
 * process boundary when nothing does.
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
                throw new InvariantViolationException(
                    'The middleware pipeline was built without a terminal request handler.',
                );
            }

            return $this->requestHandler->handle($request);
        }

        $next = new self($this->stack, $this->requestHandler, $this->index + 1);

        return $this->stack[$this->index]->process($request, $next);
    }
}
