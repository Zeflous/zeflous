<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/** Maps and safely renders an error, with a plain-text last-resort response. */
final class ErrorEnvelope
{
    /** The prefix every double-failure SAPI log line carries. */
    private const string CONSTRUCTION_FAILURE_PREFIX = 'ZEF error response construction failed: ';

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
        } catch (Throwable $throwable) {
            // The fallback is observable: the construction failure's class
            // is named on the SAPI log (prefixed, so no later grep can
            // mistake it for the application's own failure) -- the same
            // visibility doctrine the mapping failure's line carries.
            error_log(self::CONSTRUCTION_FAILURE_PREFIX . $throwable::class);
            $response = FallbackResponse::internalError($correlationId);
        }

        $message = $response->withHeader('X-Request-ID', $correlationId);
        \assert($message instanceof ResponseInterface, 'withHeader() must preserve the response type.');

        return $message;
    }
}
