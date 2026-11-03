<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\View;

use Zef\Framework\View\ViewException;
use Zef\Framework\View\ViewName;

/**
 * The native answer's resolution fold: the seam that turns one
 * extension-free logical identity into the one `.php` file the
 * engine may include -- the ADAPTER's own resolution, never the
 * port's (a future Twig answer resolves the same identity through
 * its own loader, and the name the caller spelled never changes).
 *
 * The fold is the lane's SECOND wall, and it trusts the first not at
 * all: the name grammar ({@see \Zef\Framework\View\ViewName}) could
 * never spell an escape (`..` never constructs, empty segments never
 * construct), and this fold's realpath containment still refuses one
 * -- because the only spelling left to a hostile tree is a symlink
 * planted inside the root and pointing outside it, and containment
 * is the wall that catches exactly that. A symlink planted inside
 * the root and pointing INSIDE it is the deployment's own alias, and
 * the fold answers it: containment is the boundary the engine owns,
 * the tree's own geometry is the deployment's business.
 *
 * The miss names the NAME, never the resolved address: the caller's
 * own spelling is the whole context it owns, and the resolved path
 * is the deployment's layout -- a refusal that quotes it would
 * disclose what the lane's privacy posture exists to keep.
 */
final class ViewTemplateFile
{
    /** The native answer's own file extension -- the adapter's vocabulary, never the port's. */
    private const string TEMPLATE_EXTENSION = '.php';

    /**
     * Resolves one template identity into the canonical file address
     * inside the root, refusing the miss and the escape.
     *
     * @param string $resolvedRoot the root {@see ViewTemplateDirectory::resolve()} answered
     * @param ViewName $name       the template's logical identity
     *
     * @throws ViewException when the template does not answer under the root, or
     *                       escapes it
     */
    public static function resolve(string $resolvedRoot, ViewName $name): string
    {
        $candidate = $resolvedRoot . '/' . $name->spelling() . self::TEMPLATE_EXTENSION;

        if (!is_file($candidate) || !is_readable($candidate)) {
            throw new ViewException(
                'The view template \'' . $name->spelling() . '\' does not answer'
                . ' under the templates root.',
            );
        }

        $resolved = realpath($candidate);

        if (!\is_string($resolved) || !\str_starts_with($resolved . '/', $resolvedRoot . '/')) {
            throw new ViewException(
                'The view template \'' . $name->spelling() . '\' escapes the'
                . ' templates root.',
            );
        }

        return $resolved;
    }
}
