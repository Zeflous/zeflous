<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Schema;

use Zef\Framework\Config\ConfigException;

/**
 * Generic fail-closed schema engine for configuration entries (see
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree": a configuration
 * is "tervalidasi ketat via Schema").
 *
 * A schema is a field-rule map -- field name to `[required, kind]` -- and
 * the engine checks one entry (an associative array) against it. The value
 * kinds themselves (`string`, `strings`, `string-map`, `values`) are owned
 * by {@see SchemaValueRules}; {@see RouteSchema} is the first concrete
 * schema, and a future module-configuration schema reuses the engine
 * without growing new validation code.
 *
 * Semantics (deliberate, tested):
 *
 * - The entry itself must be an array; anything else is a single violation
 *   at the `entry` address -- fail closed, never a silent skip.
 * - A required field must be present and satisfy its kind. An optional
 *   field may be absent, but when it is present it must satisfy its kind
 *   too: an optional field is optional, not exempt.
 * - A key the schema does not declare is a violation that names the
 *   schema's fields -- a typo must not vanish silently into an unvalidated
 *   field.
 * - Violations are complete and ordered: every failed expectation is
 *   reported, field rules first (in schema order), then unexpected keys (in
 *   entry order), so a message is deterministic and diffable.
 * - `validate()` is pure: it inspects the entry and never modifies it.
 *   `assert()` is the same inspection with the fail-closed exit: a
 *   `ConfigException` whose message carries every violation's path context.
 */
final readonly class ConfigSchema
{
    /**
     * @param array<non-empty-string, array{required: bool, kind: 'string'|'string-map'|'strings'|'values'}> $fields
     */
    public function __construct(
        private array $fields,
    ) {
    }

    /**
     * Returns every expectation the entry fails to satisfy, in deterministic
     * order; a valid entry yields an empty list.
     *
     * @return list<SchemaViolation>
     */
    public function validate(mixed $entry): array
    {
        if (!\is_array($entry)) {
            return [SchemaViolation::notAnArray($entry)];
        }

        return $this->violations($entry);
    }

    /**
     * Asserts that the entry satisfies the schema; otherwise throws a
     * `ConfigException` whose message carries every violation, path first.
     */
    public function assert(mixed $entry): void
    {
        $reported = $this->validate($entry);

        if ($reported === []) {
            return;
        }

        throw new ConfigException(
            'Configuration failed schema validation: ' . $this->renderViolations($reported) . '.',
        );
    }

    /**
     * Collects the field-rule violations, then the unexpected-key violations.
     *
     * @param array<array-key, mixed> $subject
     *
     * @return list<SchemaViolation>
     */
    private function violations(array $subject): array
    {
        $violations = [];

        foreach ($this->fields as $field => $rule) {
            if (\array_key_exists($field, $subject)) {
                $violations = [...$violations, ...SchemaValueRules::check($field, $rule['kind'], $subject[$field])];
            } elseif ($rule['required']) {
                $violations[] = SchemaViolation::missingField($field, $rule['kind']);
            }
        }

        return [...$violations, ...SchemaViolation::unexpectedViolations($subject, $this->fields)];
    }

    /**
     * Joins the rendered violations into one sentence, every path context
     * included; an empty list renders as an empty string, and `implode()`
     * performs the `Stringable` cast the violation's `__toString()` defines.
     *
     * @param list<SchemaViolation> $failures
     */
    private function renderViolations(array $failures): string
    {
        return implode('; ', $failures);
    }
}
