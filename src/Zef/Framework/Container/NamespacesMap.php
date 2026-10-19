<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The namespaces knob's parsed form: the `namespaces` configuration
 * entry as the lane's scope policy (ROADMAP, "CONTAINER" area 9's
 * namespace half -- the reference container's `NamespaceScopePolicy`,
 * adapted to the declarative grammar the way the interception lane
 * adapted its registries).
 *
 * The knob's grammar is a map of two keys:
 *
 *   ['namespaces' => [
 *       'scopes' => ['App\Internal\' => 'internal', 'Vendor\Module\' => 'module'],
 *       'max_cross_scope_refs' => 4,
 *   ]]
 *
 * -- the scope annotations (a namespace subtree => one of the three
 * kinds: `public` the default any service may depend on, `internal`
 * the subtree only its own members may reference, `module` the
 * budgeted subtree outside consumers may reach into), and the
 * cross-scope budget the `module` kind spends (a count of distinct
 * targets per consumer => target pair; zero denies every outbound
 * reference, the shipped profile's own default).
 *
 * Prefix spellings collapse ({@see NamespaceSpellings}) before they key
 * the map, and two spellings of one namespace position are the boot
 * refusal -- one subtree, one annotation. The annotations carry a hard
 * bound of 64, the fallback registry's own budget number, so a
 * configuration generation mistake fails the boot with a named
 * overflow instead of an unbounded policy walk.
 *
 * The empty knob -- absent, null, or an empty map -- is the valid
 * ungoverned boot: the framework declares it itself
 * ({@see \Zef\Framework\FrameworkDefaults}), a host's governance
 * arrives with the host's configuration.
 */
final readonly class NamespacesMap
{
    /** @param array<string, string> $scopes collapsed prefix => scope kind, ksorted */
    private function __construct(
        private array $scopes,
        private int $maxCrossScopeRefs,
    ) {
    }

    /**
     * The validated scope annotations: collapsed prefix => scope kind.
     *
     * @return array<string, string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * The cross-scope budget: how many distinct targets one consumer =>
     * target pair may reference into a `module` subtree (zero denies
     * every outbound reference).
     */
    public function maxCrossScopeRefs(): int
    {
        return $this->maxCrossScopeRefs;
    }

    /**
     * Parses the `namespaces` configuration entry into its validated
     * policy.
     *
     * @param mixed $source the raw `namespaces` value from the configuration
     */
    public static function parse(string $key, mixed $source): self
    {
        if ($source === null) {
            return new self([], 0);
        }

        $members = new NamespacesSource()->members($key, $source);
        $unknown = DefinitionKeys::unknownIn($members, ['scopes', 'max_cross_scope_refs']);

        if ($unknown !== null) {
            throw NamespaceRefusals::unknownKey($key, $unknown);
        }

        return new self(
            new NamespaceScopeParser()->parse($key, $members['scopes'] ?? null),
            new NamespaceBudget()->parsed($key, $members['max_cross_scope_refs'] ?? null),
        );
    }
}
