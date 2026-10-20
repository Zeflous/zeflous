<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/** Captures only structured, redacted request bodies that can be replayed safely. */
final class ReplayBodyCapture
{
    /**
     * @return array{string, bool} sanitized body and whether it can be replayed
     */
    public static function capture(ServerRequestInterface $serverRequest): array
    {
        $body = ReplayBodyReader::read($serverRequest);
        if ($body === null || $body === '') {
            return ['', $body !== null];
        }

        $contentType = mb_strtolower($serverRequest->getHeaderLine('Content-Type'));
        $mediaType = mb_trim(explode(';', $contentType, 2)[0]);

        try {
            return ReplayBodyDecoder::decode($mediaType, $body);
        } catch (Throwable) {
            return ['', false];
        }
    }
}
