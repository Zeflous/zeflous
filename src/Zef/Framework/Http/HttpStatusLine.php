<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The status-line grammar: the RFC 9110 status-code range (100..599),
 * the reason-phrase byte grammar, and the resolution of a response's
 * effective phrase -- an explicit phrase wins, a registered code
 * answers its {@see HttpReasonPhrases} entry, anything else answers an
 * empty phrase (which a status-line serializer renders without one).
 */
final class HttpStatusLine
{
    /**
     * @throws HttpException when the status falls outside 100..599
     */
    public static function assertCode(int $status): void
    {
        if ($status < 100 || $status > 599) {
            throw new HttpException("Invalid HTTP status code: {$status}.");
        }
    }

    /**
     * RFC 9110 section 15.1: reason-phrase = *( HTAB / SP / VCHAR /
     * obs-text ) -- CR, LF and NUL would corrupt any status-line
     * serializer.
     *
     * @throws HttpException when the phrase carries forbidden bytes
     */
    public static function assertPhrase(string $reasonPhrase): void
    {
        if (preg_match('/^[\t\x20-\x7E\x80-\xFF]*$/', $reasonPhrase) !== 1) {
            throw new HttpException('Invalid reason phrase.');
        }
    }

    /**
     * Resolves the effective reason phrase: an explicit phrase wins, a
     * registered code answers its registry entry, anything else
     * answers an empty phrase.
     */
    public static function resolvePhrase(int $code, string $reasonPhrase): string
    {
        if ($reasonPhrase !== '') {
            return $reasonPhrase;
        }

        return HttpReasonPhrases::MAP[$code] ?? '';
    }
}
