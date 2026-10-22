<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Splits a bare reg-name authority literal into its host and port.
 *
 * The bare form (`example.com:8443`) carries the host as a plain
 * reg-name with the port -- when present -- behind its single colon. The
 * reference implementation's exactly-one-colon rule is preserved: a
 * host with no colon passes unsplit, more than one colon is not a
 * reg-name shape a Host header may legally carry and is rejected as a
 * whole, and a colon followed by anything but pure digits fails closed
 * with the layer's exception, byte-identical to the reference
 * implementation (legacy `zef-framework` `Adapters.Http.HostAuthorityParser`).
 * The port's range is validated by {@see AuthorityPort}.
 */
final class BareAuthority
{
    private const string MALFORMED_HOST = 'Malformed Host header.';

    /**
     * Splits a bare authority literal.
     *
     * @param string $authority the bare literal
     *
     * @return array{0: string, 1: null|int} the host and the port
     *
     * @throws HttpException when the literal is not a bare reg-name
     *                       shape (an unbracketed IPv6 literal carries
     *                       more than one colon) or the port literal is
     *                       malformed
     */
    public static function split(string $authority): array
    {
        $parts = explode(':', $authority);

        if (\count($parts) > 2) {
            throw new HttpException(self::MALFORMED_HOST);
        }

        if (\count($parts) !== 2) {
            return [$authority, null];
        }

        $portText = $parts[1];

        if ($portText === '' || !ctype_digit($portText)) {
            throw new HttpException(self::MALFORMED_HOST);
        }

        return [$parts[0], AuthorityPort::checked($portText)];
    }
}
