<?php

declare(strict_types=1);

namespace Zef\Framework;

use InvalidArgumentException;
use Zef\Framework\Config\Cache\ConfigCache;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Storage\StorageLane;
use Zef\Framework\Storage\StorageLayout;

/**
 * The compiled caches' wiring onto the `files/**` route map: the fold
 * that answers the boot's two acceleration artifacts -- the
 * configuration cache and the container AOT cache -- from the storage
 * root the host declares at the composition seam, the integration the
 * error family's own record named as the layout's last open follow-up
 * ({@see ErrorStorageWiring}, the first; {@see
 * \Zef\Framework\Middleware\SessionStorageWiring}, the second).
 *
 * The declaration is a construction-time word, not a configuration
 * knob, because the configuration cache short-circuits the very
 * repository a knob would be read from: a cache address derived from
 * the merged configuration would demand loading the configuration to
 * learn where its own fast path lives -- the load the cache exists to
 * skip -- so the root must be spelled before the repository exists,
 * in the host glue's own hand ({@see ApplicationBootstrapper}'s
 * `storageRoot`), exactly where the two cache instances themselves
 * are already spelled. The request-time families keep reading their
 * root from the frozen repository's `storage.root` knob; the boot-time
 * artifacts keep theirs from the composition seam -- two seams
 * because the two moments are genuinely different, and a deployment
 * wanting one word behind both spells the knob from its own
 * environment in its own `storage.php`.
 *
 * The precedence is the integration doctrine's own, one-way: an
 * explicitly handed cache instance always answers verbatim, and
 * derivation only fills the seams the host left unset -- the declared
 * root provisions the boot's defaults, never an override of the
 * deployment's own words. A verb whose seam is already answered never
 * touches the filesystem at all, so a host handing both instances
 * over an unusable root boots exactly as it did before the layout
 * existed: the root is the derivation's input, not a promise to
 * validate something nobody consumes.
 *
 * The silent default is the honest no-op the migration story demands:
 * a null or empty root derives nothing, and the boot answers no cache
 * and no artifact -- every semantic exactly as it stood before the
 * layout existed, so a deployment that upgrades and declares nothing
 * observes nothing change.
 *
 * The walk is the layout's own over the WHOLE tree, fail-closed with
 * the refusing seam named: the boot answers to the same map the
 * inspector prints (`bin/zef storage:map`), not a one-lane shortcut
 * that could drift from it -- a deployment whose `cache/` lane is
 * missing or misprovisioned is refused at the boot, before a single
 * configuration file is read, rather than discovered mid-flight when
 * the first artifact write fails. Each verb resolves its own walk
 * inline over the declared root (never a shared nullable object
 * hand-off) because the two questions deserve two straight lines, not
 * a null-check dance between them.
 *
 * This fold answers WHERE, never WHETHER: the derived container AOT
 * cache is handed to the container seam exactly as an explicit one
 * would be, and the `aot.enabled` knob still governs its use -- a
 * deployment that declares a root and leaves AOT off gets the
 * configuration artifact and no container artifact, because the knob,
 * not the layout, decides what the boot accelerates.
 */
final class CacheStorageWiring
{
    /**
     * The configuration cache's canonical artifact name inside the
     * `cache` lane: the one spelling of the compiled configuration
     * the boot writes and reads.
     */
    public const string CONFIG_ARTIFACT_NAME = 'zef-config.php';

    /**
     * The container AOT cache's canonical artifact name inside the
     * `cache` lane: the one spelling of the compiled container the
     * artifact lane dumps and the boot installs.
     */
    public const string CONTAINER_AOT_ARTIFACT_NAME = 'zef-container-aot.php';

    /**
     * The boot's configuration cache: the instance the host handed
     * over verbatim when one was spelled, the storage layout's `cache`
     * lane joined with the canonical artifact name when the host
     * declared a root and left the seam unset, and null when neither
     * is declared (the no-cache posture the configuration bootstrapper
     * already owns).
     *
     * @param null|string $declaredRoot the storage layout's declared root,
     *                                  when the host spelled one -- the empty string
     *                                  declares no layout
     *
     * @throws InvalidArgumentException when the declared root's tree
     *                                  fails the layout's walk (the refusing seam named)
     */
    public static function configCache(?ConfigCache $configCache, ?string $declaredRoot): ?ConfigCache
    {
        if ($configCache instanceof ConfigCache) {
            return $configCache;
        }

        if ($declaredRoot === null || $declaredRoot === '') {
            return null;
        }

        return new ConfigCache(
            StorageLayout::resolve($declaredRoot)->path(StorageLane::Cache)
                . \DIRECTORY_SEPARATOR . self::CONFIG_ARTIFACT_NAME,
        );
    }

    /**
     * The boot's container AOT cache: the instance the host handed
     * over verbatim when one was spelled, the storage layout's `cache`
     * lane joined with the canonical artifact name when the host
     * declared a root and left the seam unset, and null when neither
     * is declared (the seam the `aot.enabled` knob's own refusal
     * already guards).
     *
     * @param null|string $declaredRoot the storage layout's declared root,
     *                                  when the host spelled one -- the empty string
     *                                  declares no layout
     *
     * @throws InvalidArgumentException when the declared root's tree
     *                                  fails the layout's walk (the refusing seam named)
     */
    public static function containerAotCache(
        ?ContainerAotCache $containerAotCache,
        ?string $declaredRoot,
    ): ?ContainerAotCache {
        if ($containerAotCache instanceof ContainerAotCache) {
            return $containerAotCache;
        }

        if ($declaredRoot === null || $declaredRoot === '') {
            return null;
        }

        return new ContainerAotCache(
            StorageLayout::resolve($declaredRoot)->path(StorageLane::Cache)
                . \DIRECTORY_SEPARATOR . self::CONTAINER_AOT_ARTIFACT_NAME,
        );
    }
}
