<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Adapters\Temp\FileTempStore;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Middleware\StorageRootDeclaration;
use Zef\Framework\Storage\StorageLane;
use Zef\Framework\Storage\StorageLayout;

/**
 * The temp family's wiring onto the `files/**` route map: the
 * composition fold that answers the lane's writer with its
 * cadenced sweep from the frozen configuration -- the `temp`
 * reservation the layout carried from its first day, the
 * priorities table's own P1 row's last remaining clause, closed
 * by the pull request that lands this class.
 *
 * The question the fold owns is the writer the family's knobs
 * arm, spelled at the profile's own service identity
 * (`temp.store`, the registration {@see FrameworkDefaults}
 * declares over this fold): the {@see TempDeclaration} census
 * reads what the host declared, and the derivation answers the
 * address -- an explicitly spelled `temp.path` verbatim, else
 * the storage layout's `temp` lane over the declared
 * `storage.root`, the same one-way precedence every family
 * integration in this cross-cut keeps. An armed writer with
 * neither an address nor a declared root is the named halt the
 * session and events families' combination refusals spelled first
 * ({@see Middleware\SessionFactory} and {@see
 * EventStorageWiring}): a directory the host never asked for is
 * never silently defaulted.
 *
 * The walk is the layout's own over the WHOLE tree ({@see
 * StorageLayout::resolve()}), fail-closed with the refusing seam
 * named -- the temp family answers to the same map the inspector
 * prints, never a one-lane shortcut that could drift from it
 * (KDD-ES4's doctrine, carried the way the error, session,
 * cache, events and uploads families carry it). The privacy
 * posture stays where it already lives ({@see
 * Adapters\Temp\TempStoreDirectory}) because this fold answers
 * WHERE, never HOW PRIVATE -- two questions, two seams, the
 * route map's own boundary, so an explicitly spelled address is
 * judged by the writer's own constructor posture rather than
 * re-spelled here.
 *
 * The silent declaration (the null retention) is a refusal with
 * the way out spelled, never a null answer: a host resolving the
 * writer's service identity is asking for the lane's mortal
 * scratch, and the honest answer to an undeclared ask is the
 * knob's own grammar -- spell how long the lane keeps its files,
 * or compose the writer by hand over the resolved address the
 * layout answers.
 *
 * @api
 */
final readonly class TempStorageWiring
{
    /**
     * Answers the armed writer from the frozen repository: the
     * mortal-scratch landing engine with its cadenced sweep over
     * the address the declaration and the route map spell
     * together.
     *
     * @throws ConfigException when the declaration is silent or
     *                         malformed, the writer is armed without an address to
     *                         compose over, or the storage map the derivation reads is
     *                         malformed
     */
    public static function store(Config $config): FileTempStore
    {
        $tempDeclaration = TempDeclaration::fromConfig($config);
        $ttlSeconds = $tempDeclaration->ttlSeconds;

        if ($ttlSeconds === null) {
            throw new ConfigException(
                'The temp.store writer is not declared: spell how long the'
                . ' lane keeps its files (temp.ttl) or compose the writer'
                . ' by hand over the layout address.',
            );
        }

        return new FileTempStore(
            self::directory($tempDeclaration->path, $config),
            $ttlSeconds,
        );
    }

    /**
     * The writer's effective directory: the explicitly spelled
     * `temp.path` verbatim, the storage layout's `temp` lane
     * address when the host declared a root and left the knob
     * empty, and the combination refusal when the writer is armed
     * with neither.
     *
     * @throws ConfigException when the writer is armed without a
     *                         directory to compose over, or the storage map the
     *                         derivation reads is malformed
     */
    private static function directory(string $declaredPath, Config $config): string
    {
        if ($declaredPath !== '') {
            return $declaredPath;
        }

        $root = StorageRootDeclaration::root($config);

        if ($root === null) {
            throw new ConfigException(
                'The temp.store writer needs a directory: declare'
                . ' storage.root or spell temp.path.',
            );
        }

        return StorageLayout::resolve($root)->path(StorageLane::Temp);
    }
}
