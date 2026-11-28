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
     * RFC 9110 section 15.1: reason-phrase = *( HTAB / SP / VCHAR /
     * obs-text ) -- CR, LF and NUL would corrupt any status-line
     * serializer.
     */
    private const string PHRASE_PATTERN = '/^[\t\x20-\x7E\x80-\xFF]*$/';

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
     * @throws HttpException when the phrase carries forbidden bytes
     */
    public static function assertPhrase(string $reasonPhrase): void
    {
        if (preg_match(self::PHRASE_PATTERN, $reasonPhrase) !== 1) {
            throw new HttpException('Invalid reason phrase.');
        }
    }

    /**
     * The worker emission path's acceptance question: whether a reason
     * phrase may ride the wire shape at all.
     *
     * The boolean twin of {@see assertPhrase()} -- the emitter's own
     * {@see HeaderGrammar::acceptable()} shape: the outbound worker lane
     * judges a phrase a foreign response may carry and must skip an
     * unacceptable one without failing the answer (the answer keeps its
     * registered phrase), so the gate answers with a boolean instead of
     * throwing.
     */
    public static function phraseIsEmittable(string $reasonPhrase): bool
    {
        return preg_match(self::PHRASE_PATTERN, $reasonPhrase) === 1;
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
