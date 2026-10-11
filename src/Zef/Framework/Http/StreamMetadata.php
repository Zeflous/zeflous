<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The engine's metadata report for one stream resource.
 *
 * An immutable snapshot: a detached or closed stream (a null resource) owns
 * no metadata -- it answers false to every capability question, null to
 * every key lookup and an empty map to the full-report request. The mode
 * capabilities are decided here once so the guarded transfer and the PSR-7
 * surface can ask without touching the engine twice.
 */
final readonly class StreamMetadata
{
    private const string WRITABLE_MODE_PATTERN = '/[waxc+]/';

    private const string READABLE_MODE_PATTERN = '/[r+]/';

    private ?string $mode;

    private bool $seekable;

    /** @var array<string, mixed> */
    private array $meta;

    /**
     * @param mixed $resource a PHP stream resource, or null when the stream
     *                        is detached or closed
     */
    public function __construct(mixed $resource)
    {
        $meta = \is_resource($resource) ? stream_get_meta_data($resource) : [];
        $this->mode = $meta['mode'] ?? null;
        $this->seekable = $meta['seekable'] ?? false;
        $this->meta = $meta;
    }

    public function seekable(): bool
    {
        return $this->seekable;
    }

    public function writable(): bool
    {
        $mode = $this->mode;

        return $mode !== null && preg_match(self::WRITABLE_MODE_PATTERN, $mode) === 1;
    }

    public function readable(): bool
    {
        $mode = $this->mode;

        return $mode !== null && preg_match(self::READABLE_MODE_PATTERN, $mode) === 1;
    }

    /**
     * One metadata key's value, or the engine's full report when the key is
     * null.
     */
    public function lookup(?string $key): mixed
    {
        return $key === null ? $this->meta : ($this->meta[$key] ?? null);
    }
}
