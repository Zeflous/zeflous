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
 * The repository is immutable (CONTRIBUTING.md rule 3): every mutation
 * returns a new instance (`with*`), matching the `final readonly` style of
 * `Kernel` and `Container`.
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

    /**
     * Returns a new repository with the value written at the dot-notation
     * key, creating missing intermediate arrays on the way down.
     */
    public function withSet(string $key, mixed $value): self;

    /**
     * Returns a new repository without the value addressed by the key; an
     * absent path is a no-op that still yields a new instance.
     */
    public function withUnset(string $key): self;

    /**
     * Returns a new repository with the value appended to the list addressed
     * by the key; the target must exist and be a list.
     */
    public function withAppend(string $key, mixed $value): self;

    /**
     * Returns a new repository with the value prepended to the list
     * addressed by the key; the target must exist and be a list.
     */
    public function withPrepend(string $key, mixed $value): self;
}
