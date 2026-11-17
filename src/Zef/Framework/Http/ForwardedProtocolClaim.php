<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The vocabulary of the forwarded protocol claim: what a trusted proxy's
 * `X-Forwarded-Proto` header line states, judged on its own terms.
 *
 * The trusted-proxy policy that guards the claim -- which peer may make
 * it, when the request's own scheme survives -- lives in
 * {@see TrustedRequestScheme}; this class owns only the judgement the
 * claim's own text carries. Of a comma-separated appended list only the
 * LAST value can claim: under the standard appending proxy that is the
 * position the trusted proxy wrote, every entry before it is
 * client-supplied -- the same walk {@see TrustedRequestHost} runs over
 * the forwarded Host. The winning value is case-folded (`HTTPS` answers
 * `https`) and narrowed to the two spellings the layer's requests carry.
 *
 * The claim is operator data, not client input: a value that is neither
 * `http` nor `https` refuses loudly with the value named -- a proxy the
 * operator vouched for spelling nonsense is a deployment defect the
 * operator must see, never a silent `http` that disarms the security
 * posture the claim was supposed to arm.
 */
final class ForwardedProtocolClaim
{
    /**
     * Answers the scheme the forwarded protocol claim states: the last
     * value of the appended list, case-folded to the two spellings the
     * layer's requests carry.
     *
     * @param string $headerLine the raw `X-Forwarded-Proto` header line
     *
     * @return 'http'|'https' the claimed scheme
     *
     * @throws HttpException when the claim is neither `http` nor `https`
     */
    public static function effective(string $headerLine): string
    {
        $claim = mb_strtolower(self::lastForwardedValue($headerLine));

        return match ($claim) {
            'http', 'https' => $claim,
            default => throw new HttpException(
                'The X-Forwarded-Proto claim a trusted proxy delivered must be http or https, '
                . "'" . $claim . "' given.",
            ),
        };
    }

    /**
     * Answers the last value of a comma-separated forwarded list: the
     * appended end is the position the trusted proxy vouches for, every
     * earlier entry is client-supplied and claims nothing -- the same
     * walk {@see TrustedRequestHost} runs over the forwarded Host.
     *
     * @param string $value the raw forwarded header value
     *
     * @return string the last, whitespace-trimmed value
     */
    private static function lastForwardedValue(string $value): string
    {
        $candidates = explode(',', $value);

        return mb_trim($candidates[\count($candidates) - 1]);
    }
}
