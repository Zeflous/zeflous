<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Parses a Host-header authority literal into its host and port.
 *
 * The grammar is the reference implementation's, move-verbatim: the RFC
 * 9112 §3.2 authority shape -- a bracketed IPv6 literal or a bare
 * reg-name, optionally followed by a port -- with the FQDN root-dot
 * tolerance (`example.com.`) and the host validity delegation whose DNS
 * alphabet accepts the underscore so intranet names validate
 * identically here and in {@see UriHostGrammar}. An empty authority
 * answers empty-with-no-port so callers apply their own fallback;
 * anything malformed fails closed with the layer's exception,
 * byte-identical to the reference implementation (legacy
 * `zef-framework` `Adapters.Http.HostAuthorityParser`).
 *
 * The reference implementation's dedicated control-character and
 * delimiter pre-check is folded into the validity delegation: every
 * byte it rejected (controls, userinfo `@`, path delimiters) also fails
 * the address-literal and DNS grammars downstream, so the observable
 * outcome -- the same exception, the same message -- is identical
 * without the redundant pass.
 *
 * The literal splitting lives in {@see BracketedAuthority} and
 * {@see BareAuthority} (with the port range in {@see AuthorityPort}),
 * the comparison form and validity in {@see HostComparison}, and the DNS
 * label grammar in {@see DnsHost}; this class is the parsing policy
 * over their answers.
 */
final class HostAuthority
{
    private const string MALFORMED_HOST = 'Malformed Host header.';

    /**
     * Parses an authority literal.
     *
     * @param string $authority the Host-header literal, whitespace-trimmed by the parser
     *
     * @return array{0: string, 1: null|int} the host (lowercased, brackets stripped) and the port
     *
     * @throws HttpException when the authority is malformed
     */
    public static function parse(string $authority): array
    {
        $authority = mb_trim($authority);

        if ($authority === '') {
            return ['', null];
        }

        [$host, $port] = str_starts_with($authority, '[')
            ? BracketedAuthority::split($authority)
            : BareAuthority::split($authority);
        $host = HostComparison::normalise($host);

        if ($host === '' || !HostComparison::isValid($host)) {
            throw new HttpException(self::MALFORMED_HOST);
        }

        return [$host, $port];
    }
}
