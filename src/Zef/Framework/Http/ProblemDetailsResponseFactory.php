<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zef\Framework\Exception\ZefException;

/** Renders HTTP failures as RFC 9457 Problem Details or a safe HTML page. */
final class ProblemDetailsResponseFactory
{
    /**
     * @throws JsonException If the problem document cannot be encoded.
     */
    public static function create(
        Throwable $throwable,
        int $status,
        ServerRequestInterface $request,
        string $requestId,
        bool $debug,
    ): ResponseInterface {
        $detail = ErrorDisclosure::message($throwable, $status, $debug);
        $errorRequestContext = $request->getAttribute(ErrorRequestContext::ATTRIBUTE);
        $traceId = self::traceId($throwable, $errorRequestContext, $requestId, $request);
        $instance = $request->getUri()->getPath();

        if (ErrorContentNegotiator::prefersHtml($request->getHeaderLine('Accept'))) {
            $response = HtmlErrorPageRenderer::render(
                $throwable,
                new HtmlErrorPageContext(
                    $status,
                    $detail,
                    $instance,
                    $traceId,
                    $debug,
                    $errorRequestContext instanceof ErrorRequestContext ? $errorRequestContext->durationMilliseconds() : 0.0,
                    $errorRequestContext instanceof ErrorRequestContext ? $errorRequestContext->queryCount() : 0,
                    $errorRequestContext instanceof ErrorRequestContext ? $errorRequestContext->exceptionCount() : 1,
                ),
            );
        } else {
            $response = ProblemDetailsJsonResponseFactory::create(
                $throwable,
                $status,
                $detail,
                $instance,
                $traceId,
                $debug,
            );
        }

        return ProblemDetailsResponseHeaders::apply($response, $throwable, $requestId);
    }

    private static function traceId(
        Throwable $throwable,
        mixed $errorRequestContext,
        string $requestId,
        ServerRequestInterface $request,
    ): string {
        $candidate = $throwable instanceof ZefException && $throwable->getTraceId() !== null
            ? $throwable->getTraceId()
            : ($errorRequestContext instanceof ErrorRequestContext ? $errorRequestContext->traceId() : $requestId);

        return preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $candidate) === 1
            ? $candidate
            : CorrelationId::for($request);
    }
}
