<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The compression family's `Vary` walk: the tokens a response about to
 * be content-encoded varies on, the handler's own plus
 * `Accept-Encoding`, deduplicated case-insensitively and in
 * first-spelling order -- the {@see VaryTokens} walk's own sibling for
 * the encoding negotiation.
 *
 * A compressed representation is one of several the same URL serves,
 * chosen by the request's `Accept-Encoding` line, so a cache that
 * stores it under the bare URL serves the gzip bytes to a client that
 * asked for identity -- the poisoning a `Vary: Accept-Encoding` line
 * exists to prevent. The walk folds the response's own tokens (the
 * `Origin` the CORS decoration may have added, the `Accept` the
 * router's 406 arm adds) into one line with the encoding token
 * appended, first spelling of each distinct token kept, exactly as
 * the CORS walk keeps them: `origin` and `Origin` are one token, and
 * the spelling a first writer chose is the spelling every later
 * reader sees.
 *
 * The walk is idempotent: a response that already varies on
 * `accept-encoding` in any spelling answers itself unchanged, so a
 * pipeline that composes the family twice decorates once.
 */
final class CompressionVary
{
    /**
     * Answers the response with `Accept-Encoding` among its `Vary`
     * tokens, or the response itself when it is already there.
     */
    public static function merge(ResponseInterface $response): ResponseInterface
    {
        $tokens = [];

        foreach ($response->getHeader('Vary') as $line) {
            foreach (explode(',', $line) as $token) {
                $token = mb_trim($token);

                if ($token === '') {
                    continue;
                }

                if (self::alreadyListed(mb_strtolower($token), $tokens)) {
                    continue;
                }

                $tokens[] = $token;
            }
        }

        if (self::alreadyListed('accept-encoding', $tokens)) {
            return $response;
        }

        $tokens[] = 'Accept-Encoding';

        $message = $response->withHeader('Vary', implode(', ', $tokens));
        \assert($message instanceof ResponseInterface, 'the with* chain must preserve the response type.');

        return $message;
    }

    /**
     * Answers whether a token's fold is already the fold of a collected
     * token.
     *
     * @param list<string> $tokens the tokens collected so far
     */
    private static function alreadyListed(string $fold, array $tokens): bool
    {
        return array_any($tokens, static fn (string $listed): bool => mb_strtolower($listed) === $fold);
    }
}
