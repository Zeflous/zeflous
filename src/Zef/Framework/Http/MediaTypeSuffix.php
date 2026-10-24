<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The structured-syntax-suffix grammar: whether a media type is
 * `application/<anything>+<suffix>` -- the RFC 6839 family the JSON and
 * XML parsers own their vendor members through (`application/vnd.api+json`,
 * `application/atom+xml`).
 *
 * The suffix walk is the replay lane's own house spelling
 * ({@see ReplayBodyDecoder}): the structured suffix is honoured only
 * under the `application/` tree, so `image/svg+xml` stays outside the
 * XML parser's ownership (an image type, not a document API type) and
 * `text/*+xml` stays outside it too -- a `text/` member with a suffix
 * is not a registered XML document family (KDD-BP8,
 * `docs/ci-notes/http-body-parser.md`).
 *
 * The comparison is byte-wise case-insensitive (`strcasecmp`) over the
 * two ASCII segments, exactly as the two form media types are matched
 * at the request factory's ingress seam ({@see RequestFactory}): the
 * fold never crosses a multibyte boundary because only the ASCII
 * spelling of the prefix and the suffix ever matches. The subtype
 * between them must be non-empty: `application/+json` is not a media
 * type, and a grammar that accepted it would own a type no client
 * legitimately sends.
 */
final class MediaTypeSuffix
{
    private const string APPLICATION_PREFIX = 'application/';

    /**
     * Whether the media type carries the structured suffix under the
     * `application/` tree.
     *
     * @param string $mediaType the bare type segment, parameters stripped
     * @param string $suffix    the structured suffix with its `+` (for
     *                          example `+json`), ASCII
     */
    public static function underApplication(string $mediaType, string $suffix): bool
    {
        $prefixLength = mb_strlen(self::APPLICATION_PREFIX);
        $suffixLength = mb_strlen($suffix);

        // The subtype between the prefix and the suffix must carry at
        // least one character: 'application/+json' owns nothing.
        if (mb_strlen($mediaType) <= $prefixLength + $suffixLength) {
            return false;
        }

        return strcasecmp(mb_substr($mediaType, 0, $prefixLength), self::APPLICATION_PREFIX) === 0
            && strcasecmp(mb_substr($mediaType, -$suffixLength), $suffix) === 0;
    }
}
