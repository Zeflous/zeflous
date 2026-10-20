<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use Zef\Framework\Exception\ZefException;

/**
 * The JSONL logging lane's own failure root: the write-path refusals the
 * secured-destination and bounded-record collaborators raise -- a symlinked
 * destination at write time, an unopenable file, an unacquirable lock, a
 * record that cannot finish or roll back -- as the framework's single
 * catchable failure type ({@see ZefException}: framework code never throws
 * a bare SPL exception, callers keep the one root to catch).
 *
 * Every failure this type answers is a permanent, local condition: retrying
 * the same write against the same destination would fail the same way, so
 * the fault lane's containment ({@see \Zef\Framework\Http\ErrorFaultLogWriter})
 * is what reacts to it -- the structured record is dropped rather than
 * escalated, and the last-resort SAPI line notes the failure class.
 *
 * The root extends the framework root, which already extends the SPL
 * {@see \RuntimeException} the lane previously threw, so the structural
 * catchability the draft kept is kept: a caller written against the SPL
 * type still catches these failures.
 */
final class JsonLogWriteException extends ZefException
{
}
