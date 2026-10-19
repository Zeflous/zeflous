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
        ?ServerRequestInterface $serverRequest = null,
    ): ResponseInterface {
        try {
            if ($serverRequest instanceof ServerRequestInterface) {
                $response = ProblemDetailsResponseFactory::create(
                    $throwable,
                    $status,
                    $serverRequest,
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

        $message = $response->withHeader('X-Request-ID', $correlationId);
        \assert($message instanceof ResponseInterface, 'withHeader() must preserve the response type.');

        return $message;
    }
}
