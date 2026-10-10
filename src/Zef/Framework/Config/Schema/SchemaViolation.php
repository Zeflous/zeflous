<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Schema;

use Override;
use Stringable;

/**
 * One failed schema expectation: the configuration address that failed, what
 * was expected there, and what was actually found.
 *
 * The triple is pure data -- fail-closed validation reports, it never repairs
 * (see `ROADMAP.md`, "Configuration System + DSL + Radix Tree": a
 * configuration is "tervalidasi ketat via Schema"). {@see ConfigSchema::validate()}
 * collects the violations, and {@see ConfigSchema::assert()} renders them into
 * the exception message, so the two never disagree about what a violation
 * looks like.
 *
 * The `path` uses the layer's dot-notation convention: a top-level field
 * (`method`), a list offset (`method.0`), or a map entry
 * (`constraints.id`). `expected` is the human-readable requirement -- the
 * words in {@see self::EXPECTATIONS} are the contract the whole schema layer
 * quotes, so a rule's expectation never disagrees with the check that
 * produced it -- and `actual` describes the offending value: `missing`, `an
 * empty string`, `an empty array`, `an unexpected key`, or a
 * `get_debug_type()` name such as `int` or `null`.
 */
final readonly class SchemaViolation implements Stringable
{
    /**
     * Human-readable requirement per value kind (the value kinds themselves
     * are checked by {@see SchemaValueRules}).
     */
    public const array EXPECTATIONS = [
        'string' => 'a non-empty string',
        'strings' => 'a non-empty list of non-empty strings',
        'string-map' => 'a non-empty map of non-empty strings',
        'values' => 'a non-empty map',
    ];

    public function __construct(
        public string $path,
        public string $expected,
        public string $actual,
    ) {
    }

    /**
     * Renders the violation for an exception message:
     * `<path>: expected <expected>, got <actual>`.
     */
    #[Override]
    public function __toString(): string
    {
        return \sprintf('%s: expected %s, got %s', $this->path, $this->expected, $this->actual);
    }

    /**
     * The violation for an entry that is not an array at all, addressed at
     * the `entry` root because no field of it was ever reached.
     */
    public static function notAnArray(mixed $entry): self
    {
        return new self('entry', 'an array', get_debug_type($entry));
    }

    /**
     * The violation for a required field that is absent: the expectation
     * quotes the kind's requirement, the actual is `missing`.
     *
     * @param 'string'|'string-map'|'strings'|'values' $kind
     */
    public static function missingField(string $field, string $kind): self
    {
        return new self($field, self::EXPECTATIONS[$kind], 'missing');
    }

    /**
     * The violation for a key the schema does not declare: the expectation
     * names the fields the schema does know, so a typo in a configuration
     * file comes with its own fix list. An integer key is addressed by its
     * string form, consistent with the dot-notation addressing of list
     * offsets.
     *
     * @param array<non-empty-string> $knownFields
     */
    public static function unexpectedKey(int|string $key, array $knownFields): self
    {
        return new self(
            (string) $key,
            'one of ' . implode(', ', $knownFields),
            'an unexpected key',
        );
    }

    /**
     * Collects the violations for every key of the entry the schema does not
     * declare, in entry order.
     *
     * @param array<array-key, mixed>        $entry
     * @param array<non-empty-string, mixed> $fields
     *
     * @return list<self>
     */
    public static function unexpectedViolations(array $entry, array $fields): array
    {
        $violations = [];

        foreach (array_keys(array_diff_key($entry, $fields)) as $unexpected) {
            $violations[] = self::unexpectedKey($unexpected, array_keys($fields));
        }

        return $violations;
    }
}
