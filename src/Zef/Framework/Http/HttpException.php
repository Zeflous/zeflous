<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Zef\Framework\Exception\ZefException;

/**
 * Raised when an HTTP message or stream operation fails.
 *
 * The HTTP layer is fail-closed: an invalid message shape, a detached or
 * unreadable stream, an unopenable resource and a failed upload move are all
 * errors, never silent fallbacks (see `ROADMAP.md`, "HTTP Layer"). This is
 * the layer's single exception root: the reference implementation's separate
 * PSR-7 `InvalidArgumentException` / `StreamException` / `UploadedFileException`
 * signals are folded into it, and callers keep the structural catchability
 * they had -- the framework root extends `RuntimeException`, exactly like
 * the legacy runtime failures did.
 *
 * The root is extensible, not final, because the error-handling lane's
 * typed signals grow beneath it ({@see PayloadTooLargeException}): a
 * refusal that must reach a status code is a class, and keeping it under
 * the layer root preserves every existing `catch (HttpException)` the
 * fold promised -- the {@see \Zef\Framework\Contracts\ContainerException}
 * family root follows the same shape. Leaf signals stay final.
 */
class HttpException extends ZefException
{
}
