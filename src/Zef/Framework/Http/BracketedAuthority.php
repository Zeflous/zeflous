<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Splits a bracketed IPv6 authority literal into its host and port.
 *
 * The bracketed form (`[::1]:8080`, RFC 3986 §3.2.2) carries the literal
 * between square brackets with the port -- when present -- behind a
 * single colon. This collaborator is the single splitting point for the
 * form: one anchored pattern states the whole literal grammar (the
 * bracketed literal, the optional pure-digit port, nothing else), so
 * every malformed shape -- an unclosed bracket, a garbage tail, a
 * non-digit port -- fails the pattern and the literal fails closed with
 * the layer's exception, byte-identical to the reference implementation
 * (legacy `zef-framework` `Adapters.Http.HostAuthorityParser`). The
 * pattern-match formulation follows the layer's grammar-collaborator
 * house style; the port's range is validated by {@see AuthorityPort}.
 */
final class BracketedAuthority
{
    private const string MALFORMED_HOST = 'Malformed Host header.';

    /**
     * The whole bracketed literal grammar: bracketed host bytes, an
     * optional `:port` with pure digits, and nothing else.
     */
    private const string BRACKETED_PATTERN = '~^\[([^]]*)\](?::(\d+))?\z~';

    /**
     * Splits a bracketed authority literal.
     *
     * @param string $authority the bracketed literal, `[`-prefixed
     *
     * @return array{0: string, 1: null|int} the host (brackets stripped) and the port
     *
     * @throws HttpException when the bracketed literal is malformed
     */
    public static function split(string $authority): array
    {
        if (preg_match(self::BRACKETED_PATTERN, $authority, $parts) === 1) {
            $portText = $parts[2] ?? '';

            return [$parts[1], $portText === '' ? null : AuthorityPort::checked($portText)];
        }

        throw new HttpException(self::MALFORMED_HOST);
    }
}
