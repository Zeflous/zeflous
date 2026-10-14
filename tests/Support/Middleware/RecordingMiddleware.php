<?php

declare(strict_types=1);

namespace Zef\Test\Support\Middleware;

use LogicException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Delegating middleware double that records its onion position: an enter
 * mark before delegating and an exit mark after the inner chain answers --
 * the evidence a pipeline test needs to assert execution order.
 *
 * The double also guards the onion's own invariant: a middleware instance
 * must never be re-entered while it is still processing. A pipeline that
 * re-dispatches the same stage (a broken index advance) would otherwise
 * recurse until the engine dies, leaving the mutation tooling with a
 * crashed process instead of a diagnosis -- the guard converts that into
 * an exception the test can report, keeping the failure observable.
 */
final class RecordingMiddleware implements MiddlewareInterface
{
    private bool $processing = false;

    public function __construct(
        private readonly string $name,
        private readonly MiddlewareTrace $middlewareTrace,
    ) {
    }

    #[Override]
    public function process(
        ServerRequestInterface $serverRequest,
        RequestHandlerInterface $requestHandler,
    ): ResponseInterface {
        if ($this->processing) {
            throw new LogicException(
                "Middleware '{$this->name}' was re-entered while still processing: "
                . 'the pipeline dispatched the same stage twice.',
            );
        }

        $this->processing = true;

        try {
            $this->middlewareTrace->enter($this->name);

            $response = $requestHandler->handle($serverRequest);

            $this->middlewareTrace->exit($this->name);
        } finally {
            $this->processing = false;
        }

        return $response;
    }
}
