<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The error mapping middleware -- the HTTP layer's third PSR-15 middleware
 * and the security phase's final slice: every failure a handler lets escape
 * becomes a proper error response instead of a fatal.
 *
 * The middleware owns the request's correlation id ({@see CorrelationId}):
 * the `X-Request-ID` claim travels onto every response it stamps, the
 * successful ones included. An {@see HttpException} escaping the handler is
 * the layer's own fail-closed rejection surfacing through the stack -- the
 * ingress budget's refusal answering 413 with its fixed title, any other
 * layer failure answering 400 with the validation text in dev mode
 * ({@see ErrorStatusMap}, {@see ErrorDisclosure}). Any other throwable is
 * an application fault: it is logged through the PSR-3 seam with its
 * message already redacted ({@see RedactedMessage}) and answered with the
 * 500 whose dev-mode diagnostics never leak credential material.
 *
 * The PSR-3 logger is the constructor seam, opt-in until the observability
 * lane wires a real sink: a wired logger that itself fails falls back to
 * the SAPI error log, and a failing error-response construction (dev-mode
 * diagnostics over invalid UTF-8 make `json_encode()` throw) falls back to
 * the plain-text 500 ({@see FallbackResponse}) so the client still gets the
 * status and the correlation id.
 *
 * OPT-IN: add the middleware to the stack of an application that wants
 * mapped error responses; it is intentionally not part of any default
 * stack. Ported from the reference implementation (legacy `zef-framework`
 * `Middleware/GlobalErrorHandler` over `Middleware/ErrorResponseFactory`,
 * with the runner's ingress 413/400 mapping folded in); the legacy's
 * `MethodNotAllowedException` arm (405 + `Allow`) has no signal to catch
 * here -- the router lane owns that exception when it lands.
 *
 * Deviations from the reference, both restructured for observability: the
 * legacy stamped the correlation id inside each arm of a duplicated
 * try/catch ladder, and its double-failure fallback wrapped only the 500 --
 * the arms are folded into one shared return whose rendering carries the
 * fallback for every status, and the reference's separate `isDebug()` query
 * on the factory became the middleware's own disclosure input (one policy
 * point, {@see ErrorDisclosure}).
 */
final readonly class ErrorMappingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null,
        private bool $debug = false,
    ) {
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $correlationId = CorrelationId::for($request);

        try {
            return $this->stamped($handler->handle($request), $correlationId);
        } catch (HttpException $exception) {
            return $this->failureAnswer($exception, ErrorStatusMap::statusFor($exception), $correlationId);
        } catch (Throwable $exception) {
            $this->log($exception, $correlationId, $request);

            return $this->failureAnswer($exception, 500, $correlationId);
        }
    }

    /**
     * Answers a mapped failure: the disclosure-approved message under the
     * status, rendered through the envelope (or its plain-text fallback)
     * and stamped with the correlation id.
     */
    private function failureAnswer(Throwable $exception, int $status, string $correlationId): ResponseInterface
    {
        $message = ErrorDisclosure::message($exception, $status, $this->debug);

        return $this->stamped($this->rendered($status, $message, $correlationId), $correlationId);
    }

    /**
     * Renders the error response, falling back to the plain-text 500 when
     * the envelope's construction itself fails (the double failure).
     */
    private function rendered(int $status, string $message, string $correlationId): ResponseInterface
    {
        try {
            return ErrorResponseFactory::create($status, $message, $correlationId);
        } catch (Throwable) {
            return FallbackResponse::internalError($correlationId);
        }
    }

    /**
     * Reports an application fault to the PSR-3 seam. The logged message is
     * already redacted -- the log side of the reference used a second,
     * richer sanitizer there; this slice folds both sides onto one engine
     * and leaves the truncation-and-control-strip posture to the
     * observability lane that owns it. A logger that itself fails is the
     * last resort: the failing class's name goes straight to the SAPI error
     * log, because there is no lower fallback left to try.
     */
    private function log(Throwable $exception, string $correlationId, ServerRequestInterface $serverRequest): void
    {
        try {
            $this->logger?->error('Unhandled application exception', [
                'exception' => $exception,
                'exception.message' => RedactedMessage::of($exception->getMessage()),
                'request_id' => $correlationId,
                'method' => $serverRequest->getMethod(),
                'path' => $serverRequest->getUri()->getPath(),
            ]);
        } catch (Throwable $throwable) {
            error_log('ZEF logging failure: ' . $throwable::class);
        }
    }

    /**
     * Stamps the correlation id onto a response.
     */
    private function stamped(ResponseInterface $response, string $correlationId): ResponseInterface
    {
        $message = $response->withHeader('X-Request-ID', $correlationId);
        \assert($message instanceof ResponseInterface, 'withHeader() must preserve the response type.');

        return $message;
    }
}
