<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Storage\StorageLane;
use Zef\Framework\Storage\StoragePaths;

/**
 * The storage map's JSON form: the tool-integration view the layout
 * inspector answers with `--json` -- the resolved root, one record
 * per lane in the vocabulary's declaration order (the spelling, the
 * canonical address, the lane's own one-line purpose), and the lane
 * count closing the document -- the stable shape a provisioning
 * script or a CI check reads the same map through, over the route
 * inspection family's one encoding spelling ({@see
 * RouteInspectionJson::encode}).
 */
final readonly class StorageMapJson
{
    /**
     * The whole resolved map as a JSON document.
     */
    public static function rendered(StoragePaths $storagePaths): string
    {
        $records = [];

        foreach (StorageLane::cases() as $lane) {
            $records[] = [
                'lane' => $lane->spelling(),
                'path' => $storagePaths->path($lane),
                'purpose' => $lane->purpose(),
            ];
        }

        return RouteInspectionJson::encode([
            'root' => $storagePaths->root(),
            'lanes' => $records,
            'count' => \count($records),
        ]);
    }
}
