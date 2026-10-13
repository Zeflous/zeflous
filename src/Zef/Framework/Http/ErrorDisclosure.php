<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Throwable;

/**
 * What a failed request's response may say: the per-status public message
 * policy.
 *
 * The 413 answer is a fixed title (the client asked for more than the
 * ingress budget allows -- there is nothing to diagnose from the message).
 * The 400 answer exposes the layer's own validation text in dev mode only
 * (those messages are framework-generated, so they carry no secrets). The
 * 500 answer exposes the failing code's message in dev mode -- always
 * through {@see RedactedMessage}, because an arbitrary exception message is
 * exactly where a handler happened to embed "password=..." (the v2.7.0 leak
 * the reference fixed); production answers the generic title. An unmapped
 * status falls to the 500 posture, the safe default.
 *
 * Attribution: legacy `zef-framework` `Middleware/ErrorResponseFactory`
 * (`create()`'s dev-mode ternary and the runner's 400/413 titles, gathered
 * into one policy point).
 */
final class ErrorDisclosure
{
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
        return match ($status) {
            413 => self::CONTENT_TOO_LARGE_TITLE,
            400 => $debug ? $throwable->getMessage() : self::INVALID_REQUEST,
            default => $debug ? RedactedMessage::of($throwable->getMessage()) : self::INTERNAL_ERROR,
        };
    }
}
