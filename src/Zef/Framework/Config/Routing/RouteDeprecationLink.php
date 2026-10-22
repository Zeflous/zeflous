<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The deprecation declaration's link judgement: one question -- can this
 * declared documentation link render as the RFC 8288 link target it
 * claims to be?
 *
 * The link is a URI-reference (RFC 8288 allows relative targets, so
 * `/deprecations/legacy` is a legal declaration), but the bytes the
 * `Link` field's own syntax reserves -- whitespace, control bytes, and
 * the `<`/`>` angle brackets that wrap the target -- are refused on the
 * declaration line: a value carrying them could never render as the link
 * it claims to be, and repairing it silently would move the route's
 * documentation target without the declarant's knowledge.
 *
 * @internal
 */
final class RouteDeprecationLink
{
    /**
     * The declared link, refused when it carries bytes the field syntax
     * reserves.
     *
     * @param non-empty-string $value the declared link member
     *
     * @return non-empty-string
     *
     * @throws ConfigException when the value cannot render as a link target
     */
    public static function validated(string $value, RoutePath $routePath): string
    {
        if (preg_match('/[\x00-\x20<>]/', $value) === 1) {
            throw new ConfigException(\sprintf(
                'The route "%s" declares a deprecation "link" that cannot render as a link target:'
                . ' it carries whitespace, a control byte, or an angle bracket ("%s").',
                $routePath->pattern(),
                $value,
            ));
        }

        return $value;
    }
}
