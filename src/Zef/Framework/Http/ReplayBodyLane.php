<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The structured-vocabulary judgment the replay decoder's walk rides:
 * which lane a media type names -- the JSON tree (the sanitiser's own
 * walk), the url-encoded form (the pair grammar the bound
 * {@see FormBodyParser::PAIR_LIMIT} reads), or nothing a structured
 * decode could read at all (the plain-text body the replay lane
 * merely records). The judgment is its own unit (the #801 round's own
 * archeology answer: the decoder grew past the difficulty bar with the
 * pair census aboard, and the routing judgment -- the one seam that
 * decides which decode runs -- is the cohesive piece that moves out,
 * the decoder keeping the decodes alone).
 */
final class ReplayBodyLane
{
    /**
     * The lane the media type names.
     *
     * The JSON lane: the exact `application/json` token or any
     * structured `application/…+json` suffix; the form lane: the exact
     * `application/x-www-form-urlencoded` token; every other spelling
     * (including the deceptive `application/json-evil` tree the
     * vocabulary's battery pins) names nothing structured.
     *
     * @return null|'form'|'json' the lane the media type names, for the
     *                            decoder's own match arms
     */
    public static function of(string $mediaType): ?string
    {
        if (
            $mediaType === 'application/json'
            || (str_starts_with($mediaType, 'application/') && str_ends_with($mediaType, '+json'))
        ) {
            return 'json';
        }

        return $mediaType === 'application/x-www-form-urlencoded' ? 'form' : null;
    }
}
