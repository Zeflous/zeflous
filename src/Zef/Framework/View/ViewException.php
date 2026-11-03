<?php

declare(strict_types=1);

namespace Zef\Framework\View;

use Override;
use Zef\Framework\Exception\CarriesHttpStatus;
use Zef\Framework\Exception\ZefException;

/**
 * Base exception for every view-lane failure: the template that does
 * not answer under the engine's root, the template that escapes it,
 * the data bag whose shape the scope refuses, the nesting the engine
 * refuses to climb past, the output-buffering discipline a template
 * disturbed, and the mid-render crash the engine wraps -- the lane's
 * whole failure story over the framework root, the same lane-local
 * family posture `ConfigException`, `JsonLogWriteException` and the
 * `DatabaseException` family carry.
 *
 * Every one of these is the internal posture (500): the template
 * name is the application's own addressing, never the client's
 * resource, so a miss is the developer's defect -- the same
 * deliberate-author posture `InternalException` spells -- and the
 * error boundary renders the typed refusal as the server error it
 * is, never a hand-built view-shaped response.
 */
final class ViewException extends ZefException implements CarriesHttpStatus
{
    #[Override]
    public function httpStatus(): int
    {
        return 500;
    }
}
