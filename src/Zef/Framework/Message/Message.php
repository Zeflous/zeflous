<?php

declare(strict_types=1);

namespace Zef\Framework\Message;

/**
 * Immutable command/query envelope of the ZEF message bus (see `ROADMAP.md`).
 *
 * Messages are plain data: they carry a name for routing and an immutable
 * payload map that handlers read but never mutate.
 */
final readonly class Message
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private string $name,
        private array $payload = [],
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->payload);
    }
}
