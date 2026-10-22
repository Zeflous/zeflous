<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Host-claim precedence semantics: how two host patterns relate as claims
 * over the same DNS namespace, and how specific a pattern's claims are.
 *
 * Exact hosts outrank parameterised and wildcard host patterns -- a literal
 * label claims one hostname, a "*" or "{...}" label claims every hostname
 * with a label in that position -- which outrank the host-agnostic fallback;
 * equal-specificity overlapping patterns are an ambiguity the claim
 * registry refuses rather than resolves.
 *
 * @internal
 */
final class RouteHostPrecedence
{
    /**
     * Whether two host patterns can match at least one identical DNS hostname.
     */
    public static function overlaps(string $left, string $right): bool
    {
        $leftLabels = explode('.', $left);
        $rightLabels = explode('.', $right);

        if (\count($leftLabels) !== \count($rightLabels)) {
            return false;
        }

        foreach ($leftLabels as $index => $leftLabel) {
            $rightLabel = $rightLabels[$index];
            $leftWildcard = RouteHostLabelKinds::isWildcard($leftLabel);
            $rightWildcard = RouteHostLabelKinds::isWildcard($rightLabel);

            if (!$leftWildcard && !$rightWildcard && $leftLabel !== $rightLabel) {
                return false;
            }
        }

        return true;
    }

    /**
     * Number of literal labels; a larger number is more specific. A "*"
     * label counts as non-literal, exactly like a named "{...}" label.
     */
    public static function specificity(string $pattern): int
    {
        return \count(array_filter(
            explode('.', $pattern),
            static fn (string $label): bool => !RouteHostLabelKinds::isWildcard($label),
        ));
    }
}
