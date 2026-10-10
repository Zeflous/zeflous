<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Merge;

use Zef\Framework\Config\DotKey;
use Zef\Framework\Config\DotWriter;

/**
 * Environment-variable source for the layered configuration builder: the
 * Env layer of Default < App < Env (see `ROADMAP.md`, "Configuration
 * System + DSL + Radix Tree").
 *
 * Only variables whose name starts with the prefix participate; everything
 * else in the environment contributes nothing, which is what makes the
 * layer optional. A matching name is reduced to its configuration address
 * by stripping the prefix, lower-casing the remainder with `mb_strtolower()`
 * (the repo-wide multi-byte string mandate: environment names are
 * conventionally ASCII, valid multi-byte names fold case correctly, and
 * bytes the fold cannot decode are substituted deterministically), and
 * reading the `__` separator as a dot: `APP_DATABASE__MYSQL__HOST`
 * becomes `database.mysql.host`. Single underscores stay part of their
 * segment (`APP_SESSION_DRIVER` becomes `session_driver`), so the
 * word-separator style remains the environment author's choice.
 *
 * Values are passed through as the raw strings the environment carries:
 * `APP_DATABASE__PORT=3306` yields the *string* `'3306'`, not the integer.
 * Casting is the schema layer's job (fail-closed validation), never a
 * silent coercion here. A variable that reduces to a malformed address --
 * the name equals the prefix, or a separator leaves an empty segment -- is
 * rejected by the shared dot-key mechanics with their usual message,
 * because it claims to configure this layer yet does not form a valid
 * address.
 *
 * Duplicate addresses resolve deterministically: variables are folded in
 * declaration order and the later write wins, so `APP_DB__HOST` and
 * `APP_db__host` both reduce to `db.host` and whichever the source lists
 * last provides the value. The nesting itself reuses the copy-on-write
 * {@see DotWriter} on top of {@see DotKey} parsing, so the environment
 * source shares the layer's path semantics instead of growing its own.
 *
 * The fold is pure: the constructor takes the variables as data, and
 * {@see self::fromEnvironment()} is the single I/O boundary that captures
 * the process environment with `getenv()`, which keeps the unit tests
 * deterministic.
 *
 * The source is the configuration layer's environment seam: the kernel
 * bootstrapper wires it into the layered builder, and host applications
 * may capture their own prefix, which is why it is marked `@api` rather
 * than being baselined or suppressed.
 *
 * @api
 */
final readonly class EnvSource
{
    /**
     * @param non-empty-string      $prefix    exact, case-sensitive variable-name prefix (`APP_`)
     * @param array<string, string> $variables raw variables, in fold order
     */
    public function __construct(
        private string $prefix,
        private array $variables = [],
    ) {
    }

    /**
     * Captures the process environment for the given prefix.
     *
     * @param non-empty-string $prefix
     */
    public static function fromEnvironment(string $prefix): self
    {
        return new self($prefix, getenv());
    }

    /**
     * Folds the matching variables into the nested configuration array.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        foreach ($this->variables as $name => $value) {
            if (!str_starts_with($name, $this->prefix)) {
                continue;
            }

            $address = mb_strtolower(str_replace('__', '.', mb_substr($name, mb_strlen($this->prefix))));
            $data = DotWriter::set($data, DotKey::parse($address), $value);
        }

        return $data;
    }
}
