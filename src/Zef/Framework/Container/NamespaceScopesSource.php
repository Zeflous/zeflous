<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `scopes` member's source validation: the map shape, the key
 * spellings, and the annotation budget -- the checks the entries loop
 * owes before it walks -- split from {@see NamespaceScopeParser} as its
 * own unit (the source-versus-entries boundary the definition
 * grammar's own map and entry validation draw).
 */
final readonly class NamespaceScopesSource
{
    /** The hard bound on declared scope annotations. */
    public const int SCOPE_BUDGET = 64;

    /**
     * The scopes member's own entries, refusing the shapes and the
     * budget the loop cannot walk. The empty map is not among the
     * shapes this unit answers for -- the empty annotation set is the
     * parser's own ungoverned boot ({@see NamespaceScopeParser}), the
     * way the empty knob is the map's ({@see NamespacesMap}); a map
     * that reaches this far is owed to carry prefix => kind pairs,
     * each prefix a string spelling (a canonical-numeric key arrives
     * as an int, and the entries loop reads keys as the prefixes they
     * spell).
     *
     * @param string $key    the knob's own key, for the refusals
     * @param mixed  $source the raw `scopes` value from the configuration
     *
     * @return array<array-key, mixed> the annotation entries, keys validated strings
     */
    public function entries(string $key, mixed $source): array
    {
        if (!\is_array($source) || array_is_list($source)) {
            throw NamespaceRefusals::scopesNotAMap($key, $source);
        }

        if (\count($source) > self::SCOPE_BUDGET) {
            throw NamespaceRefusals::scopeBudgetExceeded($key, self::SCOPE_BUDGET);
        }

        foreach (array_keys($source) as $prefix) {
            if (!\is_string($prefix)) {
                throw NamespaceRefusals::prefixNotANonEmptyString($key, $prefix);
            }
        }

        return $source;
    }
}
