<?php

declare(strict_types=1);

namespace Zef\Framework\Config;

use Override;
use Zef\Framework\Contracts\ConfigInterface;

/**
 * Immutable configuration repository (the ZEF Configuration Layer's runtime
 * value object; see `ROADMAP.md`, "Configuration System + DSL + Radix Tree").
 *
 * The API follows the ergonomics of `PHLAK/Config` (dot-notation `get`,
 * `has`, `split`, `toArray`) adapted to the ZEF doctrine: the class is
 * `final readonly` like `Kernel` and `Container`, so a repository built once
 * can be shared safely across a persistent worker's requests. Mutations use
 * the copy-on-write `with*` protocol (CONTRIBUTING.md rule 3): every mutation
 * returns a *new* instance and the original is never modified (the
 * `with*` protocol lives in the {@see ConfigMutations} trait).
 *
 * Semantics (deliberate, tested):
 * - `get` resolves the full path; a missing key, a missing intermediate
 *   segment or an intermediate scalar resolves to the given default.
 * - A `null` stored at the full path is a *found* value: `get` returns it
 *   (not the default) and `has` returns true. "Explicitly null" and "absent"
 *   stay distinguishable.
 * - Numeric segments address list offsets ("users.0.name"); an out-of-range
 *   offset resolves to the default.
 * - A malformed key (empty segment) is a programming error and throws, for
 *   every accessor -- fail closed, never a silent fallback.
 * - `split` scopes a sub-array into its own repository; the target must
 *   exist and be an array, otherwise it throws.
 * - `withSet` creates missing intermediate arrays on the way down; a scalar
 *   or null intermediate is replaced by a fresh array (the written path owns
 *   its shape). `withUnset` of an absent path is a no-op that still returns
 *   a new instance.
 * - `withAppend` and `withPrepend` require the addressed value to be a list:
 *   a missing path, a scalar or an associative map throws (fail closed).
 */
final readonly class Config implements ConfigInterface
{
    use ConfigMutations;

    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        private array $data = [],
    ) {
    }

    /**
     * Returns the value addressed by a dot-notation key, or the default when
     * the path does not resolve.
     */
    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        $result = DotKey::lookup($this->data, DotKey::parse($key));

        return $result[0] ? $result[1] : $default;
    }

    /**
     * Whether the full dot-notation path resolves to a value (including an
     * explicitly null one).
     */
    #[Override]
    public function has(string $key): bool
    {
        return DotKey::lookup($this->data, DotKey::parse($key))[0];
    }

    /**
     * Returns the sub-array addressed by the key as its own repository.
     */
    #[Override]
    public function split(string $key): self
    {
        $result = DotKey::lookup($this->data, DotKey::parse($key));

        if (!$result[0]) {
            throw new ConfigException(
                \sprintf('Cannot split configuration key "%s": it is not set.', $key),
            );
        }

        return new self($this->arrayValue($result[1], $key));
    }

    /**
     * Returns the underlying data (the "mother tongue" the Radix Tree engine
     * will consume; see `ROADMAP.md`).
     *
     * @return array<array-key, mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayValue(mixed $value, string $key): array
    {
        if (!\is_array($value)) {
            throw new ConfigException(
                \sprintf('Cannot split configuration key "%s": it is not an array.', $key),
            );
        }

        return $value;
    }
}
