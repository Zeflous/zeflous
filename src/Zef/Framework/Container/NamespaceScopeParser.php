<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `scopes` member's own parse: the annotations map's grammar,
 * split from {@see NamespacesMap} as its own unit the way the
 * definition grammar's entry parsers are theirs -- the knob's parse
 * stays thin, the annotations' vocabulary stays testable.
 *
 * The empty source -- absent, null, or an empty map -- is this unit's
 * own ungoverned boot, the answer the map's callers never see a
 * refusal for; anything else owes the source shape the entries loop
 * can walk ({@see NamespaceScopesSource} owns that shape, keys
 * included; {@see NamespacePrefixGrammar} owns the prefix's own
 * spelling).
 *
 * Each prefix is collapsed ({@see NamespaceSpellings}) under its own
 * grammar ({@see NamespacePrefixGrammar}), each kind validated against
 * the three-kind vocabulary, and one annotation per namespace position
 * is enforced with both spellings named -- the grammar the tree's own
 * annotate re-asserts over the parsed result.
 */
final readonly class NamespaceScopeParser
{
    /**
     * Parses the scopes member into its normalised annotations map.
     *
     * @param string $key    the knob's own key, for the refusals
     * @param mixed  $source the raw `scopes` value from the configuration
     *
     * @return array<string, string> collapsed prefix => scope kind, ksorted
     */
    public function parse(string $key, mixed $source): array
    {
        if ($source === null || $source === []) {
            return [];
        }

        /**
         * The entries' keys are strings by the source unit's own walk
         * -- the one shape the storage type widens away.
         *
         * @var array<string, mixed> $entries
         */
        $entries = new NamespaceScopesSource()->entries($key, $source);
        $namespacePrefixGrammar = new NamespacePrefixGrammar();
        $scopes = [];
        $spellings = [];

        $namespaceScopeKinds = new NamespaceScopeKinds();

        foreach (array_keys($entries) as $prefix) {
            $validatedKind = $namespaceScopeKinds->asserted($key, $prefix, $entries[$prefix]);
            $collapsed = $namespacePrefixGrammar->collapsed($key, $prefix);

            if (isset($scopes[$collapsed])) {
                throw NamespaceRefusals::scopeReused($key, $collapsed, $spellings[$collapsed], $prefix);
            }

            $scopes[$collapsed] = $validatedKind;
            $spellings[$collapsed] = $prefix;
        }

        ksort($scopes, \SORT_STRING);

        return $scopes;
    }
}
