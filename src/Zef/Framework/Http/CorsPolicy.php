<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The CORS origin allow-list's judgment half: which `Origin` values the
 * policy echoes back.
 *
 * The construction half lives in {@see AllowedOrigins}; this object
 * answers through it -- `*` under the echo-all policy, the origin as sent
 * when the allow-list admits it, and null when the response must not be
 * decorated (no origin, or one the policy does not allow). An empty
 * allow-list means CORS is off -- nothing is echoed, no preflight is
 * answered -- which stays an observable state rather than a silent
 * default.
 *
 * Attribution: legacy `zef-framework` `Middleware/CorsMiddleware.php`
 * (`negotiatedOrigin()` and `originIsAllowed()`), move-verbatim
 * behaviour. The reference's `allowedOrigins !== []` guard on the
 * comparison is folded away -- the comparison over an empty list already
 * answers not-allowed, so the guard was indistinguishable from its own
 * removal.
 */
final readonly class CorsPolicy
{
    /** @var list<string> the allowed origins in their comparison form */
    private array $allowedOrigins;

    private bool $allowAll;

    /**
     * @param null|list<mixed>|string $allowOrigin the allow-list entries: strings, or null for off
     *
     * @throws HttpException when an entry is not a string, or a non-wildcard entry is malformed
     */
    public function __construct(array|string|null $allowOrigin = null)
    {
        $allowedOrigins = AllowedOrigins::fromConfig($allowOrigin);
        $this->allowAll = $allowedOrigins->carriesWildcard;
        $this->allowedOrigins = $allowedOrigins->origins;
    }

    /**
     * Answers whether the policy is armed at all: a wildcard arms it, and
     * so does any normalised allow-list entry. An unarmed policy neither
     * answers preflights nor decorates responses.
     */
    public function isEnabled(): bool
    {
        return $this->allowAll || $this->allowedOrigins !== [];
    }

    /**
     * Answers the `Access-Control-Allow-Origin` value the given request
     * origin must be echoed as: `*` under the echo-all policy, the origin
     * as sent when the allow-list admits it, and null when the response
     * must not be decorated (no origin, or one the policy does not
     * allow).
     *
     * The echoed spelling is the request's own -- normalisation is the
     * comparison form only, so `HTTPS://Example.COM:443` is admitted by
     * an `https://example.com` entry and still echoed as sent.
     */
    public function echoFor(string $origin): ?string
    {
        if ($origin === '') {
            return null;
        }

        if ($this->allowAll) {
            return '*';
        }

        return $this->originIsAllowed($origin) ? $origin : null;
    }

    /**
     * Answers whether the origin's comparison form equals an allow-list
     * entry's. A malformed origin is simply not allowed -- the grammar
     * failure is the judgment, not an exception the caller must catch.
     */
    private function originIsAllowed(string $origin): bool
    {
        try {
            $comparisonForm = CorsOrigin::normalize($origin);
        } catch (HttpException) {
            return false;
        }

        return array_any(
            $this->allowedOrigins,
            static fn (string $allowedOrigin): bool => $comparisonForm === $allowedOrigin,
        );
    }
}
