<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Temp;

use Zef\Test\Support\Adapters\Temp\TempFileDoubleState;

use function chmod as engine_chmod;
use function realpath as engine_realpath;
use function rename as engine_rename;
use function scandir as engine_scandir;

/**
 * The temp lane's engine-boundary function doubles: the same
 * capture-style seam the session and uploads families' own doubles
 * spell -- the real engine answer passes through unless a test
 * armed an override in the shared state, so the suite runs against
 * the real filesystem and only the arms that need an impossible
 * answer (an unsecurable scratch, an unfinalisable rename, an
 * unscannable root, an unresolvable address) observe the doubled
 * one. The staging fold calls file_put_contents, chmod and rename
 * unqualified, the scan fold calls scandir, and the directory fold
 * calls realpath -- so PHP resolves them in the namespace first.
 *
 * The rename double's armed answer carries the session family's
 * own rule: `null` is the ENGINE-PASSTHROUGH arm (the rename really
 * happens), while `false` answers WITHOUT the engine call -- so
 * the marker's degraded-cadence arm observes a refused rename
 * without depending on a filesystem the runner cannot refuse on.
 *
 * @internal loaded by the suite's bootstrap before the first test
 *            executes; never shipped
 */
function chmod(string $filename, int $permissions): bool
{
    if (TempFileDoubleState::$chmodAnswer !== null) {
        return TempFileDoubleState::$chmodAnswer;
    }

    return engine_chmod($filename, $permissions);
}

function rename(string $from, string $to): bool
{
    if (TempFileDoubleState::$renameAnswer !== null) {
        return TempFileDoubleState::$renameAnswer;
    }

    return engine_rename($from, $to);
}

/**
 * @param 0|1 $sorting_order
 *
 * @return false|list<string>
 */
function scandir(string $directory, int $sorting_order = \SCANDIR_SORT_ASCENDING): array|false
{
    if (TempFileDoubleState::$scandirAnswer !== null) {
        return TempFileDoubleState::$scandirAnswer;
    }

    return engine_scandir($directory, $sorting_order);
}

function realpath(string $path): false|string
{
    if (TempFileDoubleState::$realpathAnswer !== null) {
        return TempFileDoubleState::$realpathAnswer;
    }

    return engine_realpath($path);
}
