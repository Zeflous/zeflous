<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The ingress policy for the request body: the maximum byte count a request
 * may deliver.
 *
 * The policy is the configuration half of the input validation gate
 * (`ROADMAP.md`, "Security & Hardening" -- max body size enforcement): the
 * factory answers it eagerly against the request's `Content-Length` claim
 * and the {@see LimitedInputStream} enforces it lazily against the bytes
 * that actually arrive, so a lying or absent header cannot smuggle an
 * oversized payload past the adapter. The default answers the reference
 * implementation's two mebibyte posture -- a request that never states a
 * policy is still limited, never unlimited.
 *
 * Attribution: legacy `zef-framework` `Adapters\Http\RequestBodyPolicy`
 * (move-verbatim, folded onto the layer's single exception root).
 */
final readonly class RequestBodyPolicy
{
    /** The default maximum: two mebibytes (2 * 1024 * 1024). */
    public const int DEFAULT_MAX_BYTES = 2097152;

    /**
     * @param int $maxBytes the maximum byte count a request body may deliver
     *
     * @throws HttpException when the maximum is not a positive byte count
     */
    public function __construct(
        public int $maxBytes = self::DEFAULT_MAX_BYTES,
    ) {
        if ($maxBytes < 1) {
            throw new HttpException('Request body maxBytes must be greater than zero.');
        }
    }

    /**
     * Answers whether a `Content-Length` header value already claims more
     * bytes than the policy allows.
     *
     * The claim is judged the way the reference implementation judges it:
     * surrounding whitespace is trimmed first (a field value may legally
     * carry OWS), and only a well-formed digit string is compared -- a
     * malformed claim is never a size signal by itself, it is left to the
     * lazy wrapper's byte counting (and to the engine, which does not
     * believe the header either).
     *
     * @param string $claim the raw `Content-Length` header value
     */
    public function claimExceeds(string $claim): bool
    {
        $claim = mb_trim($claim);

        return $claim !== '' && ctype_digit($claim) && $this->claimedBytes($claim) > $this->maxBytes;
    }

    /**
     * Answers the byte count a well-formed digit string claims.
     *
     * The narrowing cast lives behind the helper's own `int` return under
     * `strict_types`: removing it would answer a string from an integer
     * return, so the comparison's operand stays an integer by construction
     * (the house narrowing idiom -- the same shape the upload coercion and
     * the port grammar use for their digit strings).
     *
     * @param non-empty-string $digits the digit string, already grammar-checked
     */
    private function claimedBytes(string $digits): int
    {
        return (int) $digits;
    }
}
