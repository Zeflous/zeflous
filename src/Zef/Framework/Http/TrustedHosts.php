<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Stringable;

/**
 * The trusted host allow-list: host-header pinning as one object.
 *
 * The security half of the ingress gate (`ROADMAP.md`, "Host Header
 * Validation" -- a whitelist judged against the application's configured
 * domains, the cache-poisoning and password-reset-poisoning defence): a
 * host is trusted only when its comparison form equals the comparison
 * form of an allow-list entry. The comparison form is the reference
 * implementation's, move-verbatim -- trimmed, lowercased (the layer's
 * full Unicode case-map fold, so the Kelvin sign compares like the ASCII
 * `k` it lower-cases to), and with IPv6 brackets stripped so a Host
 * header authority (`[::1]:8080`) and a URI host (`::1`) compare equal.
 *
 * The two early-outs are deliberate defaults, now explicit: an empty
 * allow-list means pinning is not configured (compositions that REQUIRE
 * pinning opt into {@see $failClosedOnEmptyList} and fail loudly instead
 * of silently trusting every Host header), and an empty host likewise
 * stays accepted here because the ingress path rejects an empty request
 * host on its own before this validator ever runs. Entry validation
 * fails closed at construction -- a non-string, non-Stringable entry
 * would otherwise reach the comparison cast and coerce (legacy issues
 * #278 / #281).
 *
 * Attribution: legacy `zef-framework`
 * `Domain/Validation/TrustedHostValidator.php` (move-verbatim behaviour,
 * folded onto the layer's single exception root with byte-identical
 * message texts).
 */
final readonly class TrustedHosts
{
    /** @var list<string> the allow-list in its stored, already-narrowed form */
    private array $trustedHosts;

    /**
     * @param list<mixed> $trustedHosts          the allow-list entries; every entry must
     *                                           be a string or `Stringable`, narrowing to
     *                                           its string form at this boundary
     * @param bool        $failClosedOnEmptyList opt-in fail-closed posture for
     *                                           compositions that REQUIRE pinning: an
     *                                           empty allow-list throws instead of
     *                                           trusting every host
     *
     * @throws HttpException when an allow-list entry is neither a string nor `Stringable`
     */
    public function __construct(
        array $trustedHosts = [],
        private bool $failClosedOnEmptyList = false,
    ) {
        $this->trustedHosts = $this->narrowed($trustedHosts);
    }

    /**
     * Rejects a host that is not in the trusted allow-list.
     *
     * Named `assertTrusted()` rather than `assert()` on purpose: a bare
     * `assert()` call reads as the PHP built-in assertion function, which
     * static analysers treat as code execution. This method never
     * executes code -- it only compares strings and throws.
     *
     * @param string $host the host to judge, in any of its spellings (URI host
     *                     or Host-header authority literal)
     *
     * @throws HttpException when the host is not trusted, or pinning is
     *                       required and the allow-list is empty
     */
    public function assertTrusted(string $host): void
    {
        if ($this->trustedHosts === []) {
            if ($this->failClosedOnEmptyList) {
                throw new HttpException(
                    'Trusted host allow-list is empty — host-header pinning is misconfigured.',
                );
            }

            return;
        }

        if ($host === '') {
            return;
        }

        $comparisonForm = $this->comparisonForm($host);

        foreach ($this->trustedHosts as $trustedHost) {
            if ($comparisonForm === $this->comparisonForm($trustedHost)) {
                return;
            }
        }

        throw new HttpException("Untrusted host: {$host}.");
    }

    /**
     * Narrows the raw allow-list entries to their string forms, failing
     * closed on an entry the comparison could never honestly cast.
     *
     * @param list<mixed> $trustedHosts the raw entries as the caller supplied them
     *
     * @return list<string> the narrowed entries
     *
     * @throws HttpException when an entry is neither a string nor `Stringable`
     */
    private function narrowed(array $trustedHosts): array
    {
        $narrowed = [];

        foreach ($trustedHosts as $trustedHost) {
            if (!\is_string($trustedHost) && !$trustedHost instanceof Stringable) {
                throw new HttpException(
                    'Trusted host entries must be strings, got ' . get_debug_type($trustedHost) . '.',
                );
            }

            $narrowed[] = (string) $trustedHost;
        }

        return $narrowed;
    }

    /**
     * Answers the host's comparison form: trimmed, lowercased, and with
     * IPv6 brackets stripped (`[::1]` compares as `::1`).
     *
     * The bracket strip is byte-semantics by construction -- one anchored
     * pattern over the whole literal instead of indexed byte probes, the
     * layer's grammar-collaborator idiom.
     *
     * @param string $host the host spelling to normalise
     *
     * @return string the comparison form
     */
    private function comparisonForm(string $host): string
    {
        $host = mb_strtolower(mb_trim($host));

        if (preg_match('~^\[(.*)\]\z~s', $host, $brackets) === 1) {
            return $brackets[1];
        }

        return $host;
    }
}
