<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The label-kind vocabulary of canonical host patterns: every label a
 * canonicalised pattern carries is exactly one of three kinds, and every
 * host fold that needs a kind judgement asks here rather than re-spelling
 * the test itself.
 *
 * - a literal label -- a concrete DNS label such as "api", matching only
 *   itself;
 * - a named wildcard label -- "{tenant}", matching any single label and
 *   capturing it;
 * - an anonymous wildcard label -- "*", matching any single label and
 *   capturing nothing.
 *
 * Named and anonymous wildcards share the claim semantics
 * ({@see RouteHostPrecedence}): both match exactly one whole label, so
 * neither outranks the other and both count as non-literal for specificity.
 *
 * @internal
 */
final class RouteHostLabelKinds
{
    /**
     * Whether a canonical label is the anonymous whole-label wildcard "*",
     * which matches any single label and captures nothing.
     */
    public static function isAnonymousWildcard(string $label): bool
    {
        return $label === '*';
    }

    /**
     * Whether a canonical label is a wildcard -- named "{...}" or anonymous
     * "*" -- matching any single whole label.
     */
    public static function isWildcard(string $label): bool
    {
        return str_starts_with($label, '{') || self::isAnonymousWildcard($label);
    }
}
