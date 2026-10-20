<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Zef\Framework\Exception\ZefException;

/**
 * The replay-snapshot lane's own failure root: the exclusive-write refusals
 * {@see ReplaySnapshotWriter} raises -- a file that cannot be created
 * exclusively, secured to owner-only, locked, completed, or cleaned up
 * after a partial write -- as the framework's single catchable failure
 * type ({@see ZefException}: framework code never throws a bare SPL
 * exception, callers keep the one root to catch).
 *
 * Replay capture is best-effort by design: the error response never waits
 * on it, and the middleware's containment reacts to this type by answering
 * the original failure while the snapshot simply does not exist. The
 * type therefore carries the lane's own I/O verdicts -- never the HTTP
 * semantics of the request that triggered them -- which is why it extends
 * the framework root directly instead of joining the {@see HttpException}
 * family.
 *
 * The root extends the framework root, which already extends the SPL
 * {@see \RuntimeException} the lane previously threw, so the structural
 * catchability the draft kept is kept: a caller written against the SPL
 * type still catches these failures.
 */
final class ReplaySnapshotException extends ZefException
{
}
