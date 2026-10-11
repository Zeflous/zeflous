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
 */
final class HttpException extends ZefException
{
}
