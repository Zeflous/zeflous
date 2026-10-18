<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

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
        $traceId = ErrorTraceIdResolver::resolve($throwable, $errorRequestContext, $requestId, $request);
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

}
