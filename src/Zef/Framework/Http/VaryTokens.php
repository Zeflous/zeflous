<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The `Vary` token walk: the tokens a decorated response varies on, the
 * handler's own plus `Origin`, deduplicated case-insensitively and in
 * first-spelling order.
 *
 * `Vary` is a comma-separated field-name list, and field names are
 * case-insensitive -- `origin` and `Origin` are the same token, so the
 * first spelling of each distinct token is kept and later spellings fold
 * away. The `Origin` token the CORS decoration adds is canonical-cased
 * when the handler already varied on `origin` in any spelling, which is
 * the reference's overwrite made linear: the reference folded the list
 * into a map keyed by the lower-cased token, assigned `Origin` over the
 * key, and unwrapped the values -- a pure-normaliser pipeline whose
 * unwrap steps no consumer could observe, restructured here into a walk
 * whose every skip is a first-spelling win a test can pin. The fold is
 * computed once per token, at the collection site, and the membership
 * walk compares against that fold -- a second fold at the comparison
 * would be a call whose character-map behaviour no input could split.
 *
 * A `Vary` header line's empty members (double commas, trailing commas)
 * are not tokens and are skipped.
 *
 * Attribution: legacy `zef-framework` `Middleware/CorsMiddleware.php`
 * (`mergedVaryTokens()`), move-verbatim behaviour.
 */
final class VaryTokens
{
    /**
     * Answers the merged `Vary` tokens of a response: its own, plus
     * `Origin`.
     *
     * @return list<string> the distinct tokens, first spelling of each
     */
    public static function merged(ResponseInterface $response): array
    {
        $tokens = [];

        foreach ($response->getHeader('Vary') as $line) {
            foreach (explode(',', $line) as $token) {
                $token = mb_trim($token);

                if ($token === '') {
                    continue;
                }

                $fold = mb_strtolower($token);

                if (self::alreadyListed($fold, $tokens)) {
                    continue;
                }

                $tokens[] = $fold === 'origin' ? 'Origin' : $token;
            }
        }

        if (!self::alreadyListed('origin', $tokens)) {
            $tokens[] = 'Origin';
        }

        return $tokens;
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
