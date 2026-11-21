<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Uploads;

use Zef\Test\Support\Adapters\Uploads\UploadsFileDoubleState;

use function fileperms as engine_fileperms;
use function realpath as engine_realpath;

/**
 * The uploads lane's engine-boundary function doubles: the same
 * capture-style seam the session family's own doubles spell -- the
 * real engine answer passes through unless a test armed an
 * override in the shared state, so the suite runs against the real
 * filesystem and only the arms that need an impossible answer
 * (an unresolvable path, an unreadable permission mask) observe
 * the doubled one.
 *
 * @internal loaded by the suite's bootstrap before the first test
 *            executes; never shipped
 */
function realpath(string $path): false|string
{
    if (UploadsFileDoubleState::$realpathAnswer !== null) {
        return UploadsFileDoubleState::$realpathAnswer;
    }

    return engine_realpath($path);
}

function fileperms(string $filename): false|int
{
    if (UploadsFileDoubleState::$filepermsAnswer !== null) {
        return UploadsFileDoubleState::$filepermsAnswer;
    }

    return engine_fileperms($filename);
}
