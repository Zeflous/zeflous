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
 * by the `Content-Length` claim and lazily by the limited stream):
 * the replay lane reads the same seam over the same budget, and the
 * engine's own decoder owns the nesting grammar (KDD-BP11,
 * `docs/ci-notes/http-body-parser.md`).
 */
final class FormBodyParser implements BodyParserInterface
{
    #[Override]
    public function supports(string $mediaType): bool
    {
        return strcasecmp($mediaType, 'application/x-www-form-urlencoded') === 0;
    }

    #[Override]
    public function parse(string $body): array
    {
        /** @var array<array-key, mixed> $decoded the engine's form decode */
        $decoded = [];
        parse_str($body, $decoded);

        return $decoded;
    }
}
