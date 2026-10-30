<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Storage\StorageLane;
use Zef\Framework\Storage\StorageLayout;

/**
 * The session family's wiring onto the `files/**` route map: the
 * fold that answers the file driver's directory from the frozen
 * configuration -- the `sessions` lane's address the storage
 * layout provisions when the host declared a root, the second
 * named family integration the layout's own decision record
 * spells (the error family's wiring was the first, {@see
 * ErrorStorageWiring}).
 *
 * The precedence is the integration doctrine's own, one-way: an
 * explicitly spelled `http.session.path` always wins, and
 * derivation only fills the address the host left empty -- the
 * storage root provisions the deployment's defaults, never an
 * override of the deployment's own words. The empty answer is the
 * no-directory posture the composition seam's own combination
 * refusal names ({@see SessionFactory}: the `file` store word
 * without an address, spelled or derived, is a halt, never a
 * silently defaulted directory the host never asked for).
 *
 * The root itself is read by the declaration fold ({@see
 * StorageRootDeclaration}) and the walk is the layout's own over
 * the WHOLE tree, fail-closed with the refusing seam named: the
 * session family answers to the same map the inspector prints, not
 * a one-lane shortcut that could drift from it. The directory's
 * privacy posture stays where it already lives ({@see
 * \Zef\Framework\Adapters\Session\SessionStoreDirectory}) because
 * this fold answers WHERE, never HOW PRIVATE -- two questions, two
 * seams, the route map's own boundary.
 */
final class SessionStorageWiring
{
    /**
     * The file driver's effective directory: the explicitly spelled
     * `http.session.path` verbatim when the host named one, the
     * storage layout's `sessions` lane address when the host
     * declared a root and left the knob empty, and the empty string
     * when neither is declared (the combination refusal the factory
     * owns).
     *
     * @param string $declaredPath the policy-read `http.session.path`
     *                             knob: any address string, the empty string the derive
     *                             posture
     *
     * @throws ConfigException when the storage
     *                         map is malformed or carries an unknown knob
     */
    public static function directory(string $declaredPath, Config $config): string
    {
        if ($declaredPath !== '') {
            return $declaredPath;
        }

        $root = StorageRootDeclaration::root($config);
        if ($root === null) {
            return '';
        }

        return StorageLayout::resolve($root)->path(StorageLane::Sessions);
    }
}
