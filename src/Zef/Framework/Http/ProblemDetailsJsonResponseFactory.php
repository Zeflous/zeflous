<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zef\Framework\Exception\ZefException;

/** Builds the JSON representation of a Problem Details response. */
final class ProblemDetailsJsonResponseFactory
{
    public static function create(
        Throwable $throwable,
        int $status,
        string $detail,
        string $instance,
        string $traceId,
        bool $debug,
    ): ResponseInterface {
        $problem = [
            'type' => ProblemDetailsMetadata::typeFor($status),
            'title' => ProblemDetailsMetadata::titleFor($status),
            'status' => $status,
            'detail' => $detail,
            'instance' => $instance !== '' ? $instance : '/',
            'trace_id' => $traceId,
        ];

        if ($throwable instanceof ZefException) {
            $problem['code'] = $throwable->getErrorCode();

            if ($debug && $throwable->getDetails() !== []) {
                $problem['details'] = ErrorDetailsSanitizer::sanitize($throwable->getDetails());
            }
        }

        return new Response(
            $status,
            ['Content-Type' => 'application/problem+json; charset=utf-8'],
            json_encode($problem, \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE),
        );
    }
}
