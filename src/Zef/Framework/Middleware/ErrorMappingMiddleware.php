<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Zef\Framework\Http\CorrelationId;
use Zef\Framework\Http\ErrorEnvelope;
use Zef\Framework\Http\ErrorReplayStore;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\ErrorStatusMap;
use Zef\Framework\Http\ThirdPartyExceptionMapper;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The error mapping middleware -- the middleware lane's error-boundary face
 * and the security phase's final slice: every failure a handler lets escape
 * becomes a proper error response instead of a fatal. The face lives in the
 * composition lane (not the engine-free HTTP layer) because its answers flow
 * through the telemetry meter and the PSR-3 logger -- the same doctrine that
 * keeps CorrelationMiddleware and MetricsMiddleware here.
 *
 * The middleware owns the request's correlation id ({@see CorrelationId}):
 * the `X-Request-ID` claim travels onto every response it stamps, the
 * successful ones included. Every failure a handler lets escape resolves
 * through {@see ErrorStatusMap}'s three tiers -- a typed
 * {@see \Zef\Framework\Exception\CarriesHttpStatus} exception answers its
 * contract's status (the 404 of a missing resource, the 405 of a wrong
 * method, the 413 of the ingress budget's refusal), an untyped
 * {@see HttpException} is the layer's own fail-closed rejection answering
 * the coarse 400, and anything else is an application fault answering the
 * 500. The answer itself is rendered by {@see ErrorEnvelope} (the
 * disclosure-approved message, the JSON factory with its plain-text
 * double-failure fallback, the correlation-id stamp), and the fault half
 * is recorded by {@see ErrorFaultRecorder}.
 *
 * A PSR-3 logger may be injected through the constructor; when absent,
 * {@see ErrorFaultRecorder} emits a structured JSON record through the SAPI
 * error log. A failing error-response construction
 * (for example, an unsupported diagnostic value or recursive payload)
 * falls back to the plain-text 500 ({@see FallbackResponse}) so the client
 * still gets the status and the correlation id. Every mapped failure is
 * recorded when a logger is supplied: 4xx at warning, typed 5xx at error,
 * and unexpected throwables at critical. The log context is bounded and
 * credential-redacted before it crosses the PSR-3 boundary.
 *
 * The middleware is registered by the shipped default stack. Applications
 * can still construct it explicitly when composing a custom pipeline. Ported
 * from the reference implementation (legacy `zef-framework`
 * `Middleware/GlobalErrorHandler` over `Middleware/ErrorResponseFactory`,
 * with the runner's ingress 413/400 mapping folded in); the legacy's
 * `MethodNotAllowedException` arm (405 + `Allow`) is carried by the typed
 * family's own status now -- the router lane's dispatch translation
 * ({@see RouteDispatchHandler}) throws the refusal, and the `Allow`
 * header renders from its payload in
 * {@see \Zef\Framework\Http\ProblemDetailsResponseHeaders}.
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
    private ErrorFaultRecorder $errorFaultRecorder;

    public function __construct(
        ?LoggerInterface $logger = null,
        private bool $debug = false,
        ?MeterInterface $meter = null,
        private ?ErrorReplayStore $errorReplayStore = null,
    ) {
        $this->errorFaultRecorder = new ErrorFaultRecorder($logger, $meter);
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $correlationId = CorrelationId::for($request);
        $errorRequestContext = new ErrorRequestContext($correlationId);
        $serverRequest = $request->withAttribute(ErrorRequestContext::ATTRIBUTE, $errorRequestContext);

        try {
            return $this->stamped($handler->handle($serverRequest), $correlationId);
        } catch (Throwable $throwable) {
            try {
                $mappedThrowable = ThirdPartyExceptionMapper::normalize($throwable);
                $status = ErrorStatusMap::statusFor($mappedThrowable);
            } catch (Throwable $mappingFailure) {
                error_log('ZEF error status mapping failed: ' . $mappingFailure::class);
                $mappedThrowable = new RuntimeException('Error mapping failed.', 0, $mappingFailure);
                $status = 500;
            }

            $errorRequestContext->recordException();
            if ($this->errorReplayStore instanceof ErrorReplayStore) {
                try {
                    $errorRequestContext->setReplayId(
                        $this->errorReplayStore->save($serverRequest, $mappedThrowable, $status, $correlationId),
                    );
                } catch (Throwable) {
                    error_log('ZEF error replay capture failed.');
                }
            }

            $this->errorFaultRecorder->record($mappedThrowable, $status, $correlationId, $serverRequest);

            return ErrorEnvelope::answered(
                $mappedThrowable,
                $status,
                $this->debug,
                $correlationId,
                $serverRequest,
            );
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
