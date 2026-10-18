<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/** Maps and safely renders an error, with a plain-text last-resort response. */
final class ErrorEnvelope
{
    public static function answered(
        Throwable $throwable,
        int $status,
        bool $debug,
        string $correlationId,
        ?ServerRequestInterface $request = null,
    ): ResponseInterface {
        try {
            if ($request !== null) {
                $response = ProblemDetailsResponseFactory::create(
                    $throwable,
                    $status,
                    $request,
                    $correlationId,
                    $debug,
                );
            } else {
                $response = ErrorResponseFactory::create(
                    $status,
                    ErrorDisclosure::message($throwable, $status, $debug),
                    $correlationId,
                );
            }
        } catch (Throwable) {
            $response = FallbackResponse::internalError($correlationId);
        }

        $stamped = $response->withHeader('X-Request-ID', $correlationId);
        \assert($stamped instanceof ResponseInterface, 'withHeader() must preserve the response type.');

        return $stamped;
    }
}
