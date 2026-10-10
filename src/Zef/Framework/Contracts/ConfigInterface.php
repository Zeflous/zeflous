<?php

declare(strict_types=1);

namespace Zef\Framework\Contracts;

/**
 * Runtime contract of the ZEF configuration repository.
 *
 * The configuration layer's value object implements this contract (see
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree"): the container and
 * the kernel consume the abstraction, not the concrete repository, so the
 * storage shape can evolve without touching consumers.
 *
 * The contract is the framework's public API: host applications call it from
 * outside the analysed source tree, which is why it is marked `@api` rather
 * than being baselined or suppressed.
 *
 * @api
 */
interface ConfigInterface
{
    /**
     * Returns the value addressed by a dot-notation key, or the default when
     * the path does not resolve.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Whether the full dot-notation path resolves to a value.
     */
    public function has(string $key): bool;

    /**
     * Returns the sub-array addressed by the key as its own repository.
     */
    public function split(string $key): self;

    /**
     * Returns the underlying configuration data.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array;
}
