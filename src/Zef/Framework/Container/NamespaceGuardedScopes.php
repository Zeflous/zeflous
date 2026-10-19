<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The guarded-prefix question: an identifier's own guarded (internal
 * or module) collapsed prefix, or the empty bucket an unscoped or
 * public consumer shares -- split from
 * {@see NamespaceTreeScopes} as its own unit so the nearest-ancestor
 * walk stays the annotation query and the guarded-prefix grouping the
 * module budget reads stays its own concern.
 */
final readonly class NamespaceGuardedScopes
{
    /**
     * The identifier's own guarded collapsed prefix, or ''.
     *
     * @param array<string, string> $annotations collapsed prefix => scope kind
     * @param string                $collapsed   the identifier's collapsed spelling
     */
    public function guarded(array $annotations, string $collapsed): string
    {
        $scope = new NamespaceTreeScopes()->nearest($annotations, $collapsed);

        if (!$scope instanceof NamespaceScope || $scope->kind === NamespaceScopeKinds::SCOPE_PUBLIC) {
            return '';
        }

        return $scope->prefix;
    }
}
