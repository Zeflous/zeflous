<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\StreamInterface;

/**
 * The HEAD answer's own body: a view that knows the length of the
 * representation its GET twin would ship and answers no content at all
 * (issue #801's finding 1, RFC 9110 section 9.3.2 -- a HEAD response
 * SHOULD carry a Content-Length equal to its GET twin's, while the wire
 * carries none of the octets).
 *
 * The stream's {@see self::getSize()} answers the GET-equivalent length
 * so the emitter's length reconciliation -- which measures the stream,
 * not the wire -- keeps the framing header the empty swap's zero-size
 * stream made it drop: a HEAD body of size 28 with a declared
 * Content-Length of 28 is reconciled truth, not a lie, because the
 * bytes the HEAD wire owes are zero BY PROTOCOL while the length it
 * reports describes the GET twin. Every read answers the empty string
 * at end-of-stream: nothing is ever shipped, and the cursor rests at
 * zero for the emitter's rewind pump.
 */
final readonly class HeadBody implements StreamInterface
{
    public function __construct(private int $size)
    {
    }

    #[Override]
    public function __toString(): string
    {
        return '';
    }

    #[Override]
    public function close(): void
    {
        // The view holds no engine resource; there is nothing to close.
    }

    #[Override]
    public function detach(): mixed
    {
        return null;
    }

    /**
     * The GET twin's representation length: what the HEAD answer's
     * Content-Length reports (the wire carries none of it) -- never null,
     * the view was constructed with the measured length or the honest zero
     * the unmeasurable body's strip rides.
     */
    #[Override]
    public function getSize(): int
    {
        return $this->size;
    }

    #[Override]
    public function tell(): int
    {
        return 0;
    }

    #[Override]
    public function eof(): bool
    {
        return true;
    }

    #[Override]
    public function isSeekable(): bool
    {
        return true;
    }

    #[Override]
    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        // The view's cursor rests at zero by construction; any seek lands
        // there too, the empty stream's own semantics.
    }

    #[Override]
    public function rewind(): void
    {
        // The view's cursor rests at zero by construction.
    }

    #[Override]
    public function isWritable(): bool
    {
        return false;
    }

    #[Override]
    public function write(string $string): int
    {
        return 0;
    }

    #[Override]
    public function isReadable(): bool
    {
        return true;
    }

    #[Override]
    public function read(int $length): string
    {
        return '';
    }

    #[Override]
    public function getContents(): string
    {
        return '';
    }

    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        return null;
    }
}
