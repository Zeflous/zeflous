<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The authorisation request: what the middleware asks a policy to
 * authorise -- ported from the reference's `SecurityRequest` (legacy
 * `zef-main` `Framework/Security/Distributed/SecurityRequest.php`),
 * every bound held and the sensitive-material census extracted into
 * its own unit ({@see AuthAttributes}) the way the family's other
 * readings are.
 *
 * The four labelled fields are the policy's inputs:
 * `operationClass` (the method and path as one label, ≤128), the
 * `resource` (the path, ≤128), the `action` (the method, ≤64), and
 * the `replayId` (≤128, nullable -- no id carried, no protection
 * asked). The attributes are the application's own extension
 * surface, scalar or null values only, under the census's own
 * bounds.
 */
final readonly class AuthRequest
{
    /** The operation label's ceiling. */
    public const int MAX_OPERATION_CHARS = 128;

    /** The resource's ceiling. */
    public const int MAX_RESOURCE_CHARS = 128;

    /** The action's ceiling. */
    public const int MAX_ACTION_CHARS = 64;

    /** The replay id's ceiling. */
    public const int MAX_REPLAY_ID_CHARS = 128;

    /**
     * @param array<array-key, mixed> $attributes the application's own
     *                                            labelled extension
     *                                            surface, scalar or null
     *                                            values only
     *
     * @throws HttpException when a bound is violated or an attribute
     *                       key spells sensitive material
     */
    public function __construct(
        public string $operationClass,
        public string $resource,
        public string $action,
        public ?string $replayId = null,
        public array $attributes = [],
    ) {
        $this->assertBounded($this->operationClass, self::MAX_OPERATION_CHARS, 'operationClass');
        $this->assertBounded($this->resource, self::MAX_RESOURCE_CHARS, 'resource');
        $this->assertBounded($this->action, self::MAX_ACTION_CHARS, 'action');

        if (self::carriesAMalformedReplayId($this->replayId)) {
            throw new HttpException('The security.auth request replayId exceeds its bound.');
        }

        AuthAttributes::census($this->attributes);
    }

    /**
     * The boundary's request from the request's own labels: the
     * method-and-path operation, the resource and the action, each
     * truncated to the bound this value object holds -- the
     * reference's untruncated operation label crashed the
     * constructor on any path longer than the label's bound, and a
     * truncated label is the policy's input, not the client's.
     *
     * @param string $method     the request's method, case already folded
     * @param string $path       the request's path
     * @param string $replayLine the raw `X-Replay-Id` header line, the
     *                           empty string when none was carried
     */
    public static function fromRequest(string $method, string $path, string $replayLine): self
    {
        return new self(
            mb_strcut($method . ' ' . $path, 0, self::MAX_OPERATION_CHARS),
            $path === '' ? '/' : mb_strcut($path, 0, self::MAX_RESOURCE_CHARS),
            mb_strcut($method, 0, self::MAX_ACTION_CHARS),
            $replayLine === '' ? null : $replayLine,
        );
    }

    /**
     * Whether a replay id is malformed at the request's own bound: the
     * empty spelling or one over the ceiling -- null (no id carried)
     * is never malformed, exactly as the constructor holds it. The
     * replay protector reads this fail-closed ({@see
     * BoundedReplayProtector}), and this value object's own
     * constructor reads it the same way.
     */
    public static function carriesAMalformedReplayId(?string $replayId): bool
    {
        if ($replayId === null) {
            return false;
        }

        return $replayId === '' || mb_strlen($replayId) > self::MAX_REPLAY_ID_CHARS;
    }

    /**
     * One labelled field's non-empty bound, as the reference asserted
     * it.
     *
     * @param string $value the field under the bound
     * @param int    $max   the field's ceiling, in characters
     * @param string $field the field's own name, as the rejection
     *                      spells it
     */
    private function assertBounded(string $value, int $max, string $field): void
    {
        if ($value === '' || mb_strlen($value) > $max) {
            throw new HttpException('The security.auth request ' . $field . ' exceeds its bound.');
        }
    }
}
