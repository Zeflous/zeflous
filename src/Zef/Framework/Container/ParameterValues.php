<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `parameters` knob's parsed form: the flat, dot-addressed map of
 * injectable values the compile pass reads (see {@see ParameterMap}
 * for the parse).
 *
 * The container's half of the configuration seam: the kernel-layer
 * bootstrapper hands this plain-data map to the autowiring compile
 * pass, so the pass answers `#[Value]` lookups without the container
 * layer ever learning the configuration layer exists (the deptrac
 * build order -- the same reason-free seam the four-shape grammar
 * itself uses).
 *
 * Every key is grammar-valid by construction: the constructor enforces
 * the {@see ParameterKeys} grammar, so a malformed address cannot ride
 * a value into the compile pass under a spelling the `#[Value]`
 * attribute could never match.
 */
final readonly class ParameterValues
{
    /**
     * @param array<string, mixed> $values address => value (a scalar, null, or an array of such)
     */
    public function __construct(
        private array $values = [],
    ) {
        foreach (array_keys($this->values) as $address) {
            ParameterKeys::assert("The 'parameters' address", $address);
        }
    }

    /** The no-parameters map: the knob's absent-or-null form. */
    public static function empty(): self
    {
        return new self([]);
    }

    /** Whether the map carries a value for the address. */
    public function has(string $address): bool
    {
        return isset($this->values[$address]) || \array_key_exists($address, $this->values);
    }

    /**
     * The address's value (null included: an explicitly-null parameter
     * is honest data, not an absent one).
     */
    public function value(string $address): mixed
    {
        return $this->values[$address];
    }

    /**
     * @return list<string> the addresses, in declaration order
     */
    public function addresses(): array
    {
        return array_keys($this->values);
    }

    /**
     * @return array<string, mixed> the flat map itself (the report and
     *                              test surface; the pass reads through
     *                              the accessors above)
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
