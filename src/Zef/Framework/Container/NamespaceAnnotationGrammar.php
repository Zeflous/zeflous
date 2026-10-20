<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The scope annotation's own grammar: the kind's vocabulary and the
 * prefix's governability, the checks an annotate owes before the
 * annotations map accepts it -- split from
 * {@see NamespaceTreeBuilder} as its own unit.
 */
final readonly class NamespaceAnnotationGrammar
{
    /**
     * The annotation's collapsed prefix, refusing the kinds and the
     * prefixes the map cannot carry.
     */
    public function collapsedOf(string $prefix, string $scope): string
    {
        if (!\in_array($scope, NamespaceScopeKinds::SCOPES, true)) {
            throw NamespaceBuiltRefusals::annotateUnknownKind($prefix, $scope);
        }

        $collapsed = NamespaceSpellings::prefix($prefix);

        if ($collapsed === '') {
            throw NamespaceBuiltRefusals::annotateRootPrefix($prefix);
        }

        return $collapsed;
    }
}
