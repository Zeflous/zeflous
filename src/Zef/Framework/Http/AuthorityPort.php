<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Answers the port an authority literal's digits describe.
 *
 * Pure digits are not yet a port: the value must sit in the legal
 * 1..65535 range, and the literal parsers reject anything else fail-closed
 * with the layer's exception, byte-identical to the reference
 * implementation (legacy `zef-framework` `Adapters.Http.HostAuthorityParser`).
 */
final class AuthorityPort
{
    private const string MALFORMED_HOST = 'Malformed Host header.';

    /**
     * Answers the port for a literal parser's digit text.
     *
     * @param string $portText the port digits an authority literal carried
     *
     * @return int the port in its legal range
     *
     * @throws HttpException when the digits are not a legal port
     */
    public static function checked(string $portText): int
    {
        $port = (int) $portText;

        if ($port < 1 || $port > 65535) {
            throw new HttpException(self::MALFORMED_HOST);
        }

        return $port;
    }
}
