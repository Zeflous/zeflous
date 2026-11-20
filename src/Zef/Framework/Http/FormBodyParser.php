<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;

/**
 * The url-encoded form strategy: `application/x-www-form-urlencoded`,
 * deserialized by the engine's own form decoder into the array the PSR
 * contract's parsed body carries -- the same decode the replay lane
 * reads ({@see ReplayBodyDecoder::form()}) and the same media type the
 * request factory honours for the SAPI-decoded `$_POST` bag
 * ({@see RequestFactory::formBody()}).
 *
 * The strategy's honest role is the NON-SAPI path: a request built
 * from explicitly supplied sources with no parsed body (the
 * persistent-worker twin), or a foreign PSR-7 request the ingress
 * guard hands the application, arrives with the form body still on its
 * stream -- and this strategy decodes it there. When the SAPI already
 * decoded the form, the factory's seam set the parsed body and the
 * middleware's already-parsed gate passes the request through
 * untouched: the engine's own answer is never re-parsed, and this
 * strategy never fires on the standard path at all.
 *
 * The decode is `parse_str()` over a body the ingress policy already
 * budget-bounded (the two-mebibyte default posture, enforced eagerly
 * by the `Content-Length` claim and lazily by the limited stream),
 * with the pair bound the SAPI decoder would have enforced and the
 * userland decode must own for itself (issue #801's finding 3): the
 * engine's `max_input_vars` posture bounds the SAPI-decoded bag at a
 * thousand pairs, while a userland `parse_str` over an ampersand
 * storm (`a[0]=1&a[1]=1&...`) decodes the whole two-mebibyte budget
 * into ~260k array entries -- the memory amplification the SAPI
 * exists to stop, reproduced inside the process this strategy
 * decodes in. The bound is counted on the raw string BEFORE any
 * decode allocates ({@see self::pairCount()}) -- the count itself
 * must never pay the amplification it exists to refuse -- and a
 * body whose count exceeds the bound refuses fail-closed with the
 * malformed-body signal (the family's own 400, the grammar this
 * strategy owns; the count is conservative: raw ampersands
 * overcount the real pair set, so a junk body of empty members
 * refuses a little early, the fail-closed direction the family
 * keeps). The replay lane reads its own captured bodies through the
 * same bound ({@see ReplayBodyDecoder::form()} censuses before it
 * decodes, an over-bound capture answering not-replayable rather
 * than refusing -- the failure lane the replay family exists to
 * serve never throwing), and the engine's own decoder owns the
 * nesting grammar (KDD-BP11, `docs/ci-notes/http-body-parser.md`).
 */
final class FormBodyParser implements BodyParserInterface
{
    /**
     * The pair bound the engine's own `max_input_vars` posture spells
     * (its default), the SAPI-equivalent ceiling the userland decode
     * holds for itself.
     */
    public const int PAIR_LIMIT = 1000;

    #[Override]
    public function supports(string $mediaType): bool
    {
        return strcasecmp($mediaType, 'application/x-www-form-urlencoded') === 0;
    }

    #[Override]
    public function parse(string $body): array
    {
        if (self::pairCount($body) > self::PAIR_LIMIT) {
            throw new MalformedBodyException(
                'The url-encoded body carries more than ' . self::PAIR_LIMIT . ' pairs ('
                . 'the max_input_vars posture the SAPI decoder would have enforced), '
                . 'the userland decode refusing the amplification instead of paying it.',
            );
        }

        /** @var array<array-key, mixed> $decoded the engine's form decode */
        $decoded = [];
        parse_str($body, $decoded);

        return $decoded;
    }

    /**
     * The raw pair count the bound reads: ampersands plus one, counted
     * by the engine's own byte scan -- no member array is ever
     * allocated, the count itself never paying the amplification it
     * exists to refuse (a body of empty members overcounts its real
     * pairs, the conservative direction the fail-closed bound keeps).
     * The replay decoder's own form walk censuses through the same
     * spelling ({@see ReplayBodyDecoder::form()}).
     */
    public static function pairCount(string $body): int
    {
        return mb_substr_count($body, '&') + 1;
    }
}
