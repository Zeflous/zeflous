<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The route host grammar: canonicalisation of declared host patterns and
 * untrusted request host values.
 *
 * Route patterns support exact DNS labels, whole-label parameters such as
 * "{tenant}.example.com", and whole-label anonymous wildcards such as
 * "*.example.com" (one label per "*", the RFC 6125 wildcard reading -- never
 * a partial label and never several labels). Ports, IP literals with
 * brackets, userinfo and scheme spellings are rejected so matching has one
 * deterministic grammar (the label rules every other host fold shares).
 *
 * @internal
 */
final class RouteHostGrammar
{
    private const string LABEL_PARAMETER = '/\A\{([a-zA-Z][a-zA-Z0-9_]{0,63})\}\z/D';

    private const string DNS_LABEL = '/\A[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\z/D';

    private const int MAXIMUM_HOST_BYTES = 253;

    private const int MAXIMUM_LABELS = 127;

    /**
     * Validates and canonicalises a DNS host pattern.
     *
     * @return non-empty-string
     */
    public static function canonical(string $host): string
    {
        if ($host === '' || mb_strlen($host) > self::MAXIMUM_HOST_BYTES) {
            throw self::invalid($host);
        }

        $labels = explode('.', $host);
        $canonical = [];
        $seenParameters = [];

        foreach ($labels as $label) {
            if (RouteHostLabelKinds::isAnonymousWildcard($label)) {
                $canonical[] = '*';

                continue;
            }

            if (preg_match(self::LABEL_PARAMETER, $label, $matches) === 1) {
                $parameter = mb_strtolower($matches[1]);
                if (isset($seenParameters[$parameter])) {
                    throw self::invalid($host);
                }

                $seenParameters[$parameter] = true;
                $canonical[] = '{' . $parameter . '}';

                continue;
            }

            if (preg_match(self::DNS_LABEL, $label) !== 1) {
                throw self::invalid($host);
            }

            $canonical[] = mb_strtolower($label);
        }

        if (\count($canonical) > self::MAXIMUM_LABELS) {
            throw self::invalid($host);
        }

        return self::canonicalisedHost(implode('.', $canonical), $host);
    }

    /**
     * Narrows the joined host to a non-empty string, the shape companion of
     * {@see RouteEntryCollections::nonEmptyString()}:
     * the label grammar above canonicalises a non-empty host through
     * non-empty labels, so the join is belt-and-braces guarded here rather
     * than trusted, and the refusal keeps the ordinary invalid-host message.
     *
     * @return non-empty-string
     */
    private static function canonicalisedHost(string $canonicalHost, string $host): string
    {
        if ($canonicalHost === '') {
            throw self::invalid($host);
        }

        return $canonicalHost;
    }

    private static function invalid(string $host): ConfigException
    {
        return new ConfigException(\sprintf(
            'The route host "%s" must be an exact DNS hostname, whole-label parameters, '
            . 'or whole-label "*" wildcards, without a port.',
            $host,
        ));
    }
}
