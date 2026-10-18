<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Throwable;
use Zef\Framework\Exception\CarriesHttpStatus;

/**
 * What a failed request's response may say: the public message policy.
 *
 * The 413 answer is a fixed title (the client asked for more than the
 * ingress budget allows -- there is nothing to diagnose from the message).
 * A typed {@see CarriesHttpStatus} exception carries a message written for
 * the client by contract, so it is shown in both modes -- always through
 * {@see RedactedMessage}, because an author who embedded "password=..." in
 * a client-facing message must be stopped by the policy, not by review (the
 * v2.7.0 leak lesson, generalised from the 500 arm to the typed family).
 * The 400 answer of an untyped {@see HttpException} exposes the layer's own
 * validation text in dev mode only (those messages are framework-generated,
 * so they carry no secrets). The 500 answer exposes the failing code's
 * message in dev mode; production answers the generic title. An unmapped
 * status falls to the 500 posture, the safe default.
 *
 * Attribution: legacy `zef-framework` `Middleware/ErrorResponseFactory`
 * (`create()`'s dev-mode ternary and the runner's 400/413 titles, gathered
 * into one policy point); the typed-family arm is the error-handling lane's
 * addition (ROADMAP.md, "Error Handling System", phase 1).
 */
final class ErrorDisclosure
{
    private const int CONTENT_TOO_LARGE = 413;

    private const string CONTENT_TOO_LARGE_TITLE = 'Content Too Large';

    private const string INVALID_REQUEST = 'Invalid request.';

    private const string INTERNAL_ERROR = 'An error occurred';

    /**
     * Answers the public message for a failure that maps to the status.
     *
     * @param bool $debug whether dev-mode diagnostics are enabled
     */
    public static function message(Throwable $throwable, int $status, bool $debug): string
    {
        if ($status === self::CONTENT_TOO_LARGE) {
            return self::CONTENT_TOO_LARGE_TITLE;
        }

        if ($throwable instanceof CarriesHttpStatus) {
            return self::redact($throwable->getMessage());
        }

        return match ($status) {
            400 => $debug ? self::redact($throwable->getMessage()) : self::INVALID_REQUEST,
            default => $debug ? self::redact($throwable->getMessage()) : self::INTERNAL_ERROR,
        };
    }

    private static function redact(string $message): string
    {
        return RedactedMessage::of(substr($message, 0, 2048));
    }
}
