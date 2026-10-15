<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The auth family's refusal answer: the status a failure earns, the
 * reason phrase the envelope carries, the RFC 6750 challenge a 401
 * or 403 demands, and the retry window the outages answer with --
 * extracted from the middleware as its own unit the way the family's
 * other answers are ({@see ErrorResponseFactory}'s envelope, {@see
 * CorrelationId}'s id).
 *
 * The statuses follow the boundary's own taxonomy: the
 * authentication refusals earn the 401 a client can act on
 * (re-present or re-authenticate), the authorization and replay
 * refusals the 403 an authenticated client earned, the outage
 * failures the fail-closed 503 with a one-second retry window (the
 * rate-limit family's own outage posture -- the reference answered
 * the provider's unavailability with a 401 the client could only
 * retry identically), and the malformed-metadata failure -- the
 * contract vocabulary no boundary arm produces -- the 500 that an
 * invariant break honestly is.
 *
 * The challenges are RFC 6750's own spellings: a bare `Bearer` when
 * no credential was presented (the client needs to know the scheme
 * the boundary speaks), `error="invalid_token"` when one was
 * presented and the provider refused it, `error="insufficient_scope"`
 * when the authenticated principal's grant did not cover the
 * request. The replay refusal carries no challenge: it is not an
 * authentication question.
 */
final readonly class AuthRefusal
{
    /** The invariant every with*() chain upholds: the PSR-7 instance type. */
    private const string PRESERVED_RESPONSE_TYPE = 'withHeader() must preserve the response type.';

    /**
     * @param null|string $challenge the WWW-Authenticate value, null when
     *                               the refusal carries none
     */
    public function __construct(
        public int $status,
        public string $message,
        public ?string $challenge,
        public bool $retryAfter,
    ) {
    }

    /**
     * The refusal an absent credential earns on an unsafe method: the
     * 401 with the bare challenge, no error code -- the client asked
     * for a protected resource without presenting anything.
     */
    public static function unauthenticated(): self
    {
        return new self(401, 'Unauthorized', 'Bearer', false);
    }

    /**
     * The refusal a boundary failure earns.
     */
    public static function for(AuthFailure $authFailure): self
    {
        return match ($authFailure) {
            AuthFailure::AUTHENTICATION_UNAVAILABLE, AuthFailure::REPLAY_UNAVAILABLE => new self(
                503,
                'Service Unavailable',
                null,
                true,
            ),
            AuthFailure::AUTHORIZATION_DENIED => new self(
                403,
                'Forbidden',
                'Bearer error="insufficient_scope"',
                false,
            ),
            AuthFailure::REPLAY_REJECTED => new self(403, 'Forbidden', null, false),
            AuthFailure::MALFORMED_METADATA => new self(500, 'Internal Server Error', null, false),
            default => new self(401, 'Unauthorized', 'Bearer error="invalid_token"', false),
        };
    }

    /**
     * The refusal's response: the layer's error envelope, the
     * challenge header when the refusal carries one, and the
     * one-second retry window the outages answer with.
     */
    public function response(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = ErrorResponseFactory::create($this->status, $this->message, CorrelationId::for($serverRequest));

        if ($this->challenge !== null) {
            $response = $response->withHeader('WWW-Authenticate', $this->challenge);
        }

        if ($this->retryAfter) {
            $response = $response->withHeader('Retry-After', '1');
        }

        \assert($response instanceof ResponseInterface, self::PRESERVED_RESPONSE_TYPE);

        return $response;
    }
}
