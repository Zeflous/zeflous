<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Container\Aot\AotOutcome;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Container\ContainerInventory;
use Zef\Framework\FrameworkDefaults;

/**
 * The container's analysis report (ROADMAP, "CONTAINER" area 12's CLI
 * item -- "Perintah CLI analisis: melihat daftar service, dependensi,
 * dan status tag"): the answer `bin/zef container:inventory` renders,
 * booted from the host's own configuration directory through the same
 * application orchestrator the runtime rides.
 *
 * The boot is the analysis's honesty: the report answers the
 * declarations the configuration actually spells, not a parallel
 * parse -- and because the boot carries the environment mode doctrine
 * ({@see \Zef\Framework\ContainerMode}), the analysis inherits it for
 * free: a development environment compiles the live source afresh,
 * a production one keeps the artifact lane's own posture. The one
 * seam the analysis owns is the artifact cache: the host's real boot
 * wires their own `ContainerAotCache` location, which the CLI cannot
 * know -- so the analysis boot wires a throwaway cache file (the
 * seam's `aot.enabled` guard stays satisfied, the full lane compiles
 * the same declarations whichever artifact the host's own boot would
 * have answered from, and the temporary file never outlives the
 * report).
 *
 * The rendering itself is the presentation units' own ({@see
 * ContainerInventoryText} the table form, {@see
 * ContainerInventoryDetail} the `--id=` form): pure presentation over
 * the inventory's own data, this unit owning the boot alone.
 */
final readonly class ContainerInventoryReport
{
    /**
     * Boots the configuration directory's container and renders its
     * inventory: the whole table, or one identifier's full entry.
     *
     * @param non-empty-string $configDirectory the host's configuration
     *                                          directory (the `app/configs` layout)
     * @param null|string      $identifier      one declared identifier's own
     *                                          entry, when the caller asks for it
     */
    public static function rendering(string $configDirectory, ?string $identifier = null): string
    {
        $artifactFile = sys_get_temp_dir() . '/zef-container-inventory-' . uniqid('', false) . '.php';

        try {
            $kernel = new ApplicationBootstrapper(
                containerAotCache: new ContainerAotCache($artifactFile),
            )->boot($configDirectory);

            $inventory = $kernel->container()->get(FrameworkDefaults::INVENTORY_SERVICE);
            $outcome = $kernel->container()->get(FrameworkDefaults::AOT_SERVICE);

            \assert($inventory instanceof ContainerInventory);
            \assert($outcome instanceof AotOutcome);

            return $identifier === null
                ? ContainerInventoryText::table($inventory, $outcome)
                : ContainerInventoryDetail::rendered($inventory, $identifier);
        } finally {
            if (is_file($artifactFile)) {
                unlink($artifactFile);
            }
        }
    }
}
