<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * The zero-library PSR-7 stream: a fail-closed wrapper over one PHP stream
 * resource.
 *
 * The PSR surface owns the resource slot and the message-level orchestration
 * ({@see __toString}); the capability answers live in {@see StreamMetadata},
 * the guarded data transfer in {@see StreamTransfer} and the positional
 * answers in {@see StreamPosition} -- the same depth-split the configuration
 * layer's radix engine uses to stay under the architecture gate's difficulty
 * budget. A detached or closed stream answers its capability questions with
 * false and fails its stateful operations loudly instead of returning
 * degraded values.
 *
 * @api
 */
final class Stream implements StreamInterface
{
    /** @var null|resource */
    private $resource;

    /**
     * @param mixed $resource a PHP stream resource; anything else is
     *                        rejected fail-closed
     */
    public function __construct(mixed $resource)
    {
        if (!\is_resource($resource)) {
            throw new HttpException('Stream resource required.');
        }

        $this->resource = $resource;
    }

    #[Override]
    public function __toString(): string
    {
        try {
            if (!$this->isReadable()) {
                return '';
            }

            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (Throwable) {
            // PSR-7: __toString() must always return a string, including on
            // the failure paths of the wrapped resource operations.
            return '';
        }
    }

    /**
     * Builds a seekable, readable and writable in-memory stream over the
     * given string.
     */
    public static function fromString(string $content): self
    {
        $stream = new self(StreamOpener::open('php://temp', 'w+b'));
        $stream->write($content);
        $stream->rewind();

        return $stream;
    }

    #[Override]
    public function close(): void
    {
        $resource = $this->resource;
        if (\is_resource($resource)) {
            fclose($resource);
        }

        $this->resource = null;
    }

    #[Override]
    public function detach(): mixed
    {
        $detached = $this->resource;
        $this->resource = null;

        return $detached;
    }

    #[Override]
    public function getSize(): ?int
    {
        return StreamPosition::size($this->resource);
    }

    #[Override]
    public function tell(): int
    {
        return StreamPosition::tell($this->resource);
    }

    #[Override]
    public function eof(): bool
    {
        return StreamPosition::eof($this->resource);
    }

    #[Override]
    public function isSeekable(): bool
    {
        return new StreamMetadata($this->resource)->seekable();
    }

    #[Override]
    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        StreamPosition::seek($this->resource, $offset, $whence);
    }

    #[Override]
    public function rewind(): void
    {
        $this->seek(0);
    }

    #[Override]
    public function isWritable(): bool
    {
        return new StreamMetadata($this->resource)->writable();
    }

    #[Override]
    public function write(string $string): int
    {
        return StreamTransfer::write($this->resource, $string);
    }

    #[Override]
    public function isReadable(): bool
    {
        return new StreamMetadata($this->resource)->readable();
    }

    #[Override]
    public function read(int $length): string
    {
        return StreamTransfer::read($this->resource, $length);
    }

    #[Override]
    public function getContents(): string
    {
        return StreamTransfer::contents($this->resource);
    }

    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        return new StreamMetadata($this->resource)->lookup($key);
    }
}
