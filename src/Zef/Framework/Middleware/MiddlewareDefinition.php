<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

/**
 * One parsed `middleware.stack` entry.
 *
 * The grammar accepts two spellings, both fail-closed:
 *
 * - the string form -- a bare service identifier (`'app.logging'`), the
 *   shorthand for a priority-0 entry in declaration order;
 * - the map form -- `['service' => id, 'priority' => int]` (the reference
 *   repository also accepted `id` as an alias of `service`; both are kept).
 *   `priority` orders the pipeline HIGHEST FIRST, ties keep declaration
 *   order; a non-numeric priority is rejected rather than defaulted.
 *
 * The reference repository's `group` and `tags` keys are deliberately NOT
 * ported: they were parsed, validated and then explicitly reserved for
 * future use (its issue #174 note) -- carrying dead keys forward would be
 * speculative grammar.
 */
final readonly class MiddlewareDefinition
{
    public function __construct(
        public string $serviceId,
        public int $priority = 0,
    ) {
        if ($serviceId === '') {
            throw new MiddlewareException('A middleware definition requires a non-empty service identifier.');
        }
    }

    /**
     * Parses one stack entry (the mixed raw value from configuration).
     */
    public static function fromArray(mixed $entry): self
    {
        if (\is_string($entry)) {
            return new self($entry);
        }

        if (!\is_array($entry)) {
            throw new MiddlewareException(
                'A middleware stack entry must be a service identifier string or a definition map, '
                . get_debug_type($entry) . ' given.',
            );
        }

        $service = $entry['service'] ?? $entry['id'] ?? null;
        if (!\is_string($service) || $service === '') {
            throw new MiddlewareException(
                "A middleware stack entry requires a non-empty 'service' (or 'id') key.",
            );
        }

        $priority = $entry['priority'] ?? 0;
        if (!is_numeric($priority)) {
            throw new MiddlewareException("Middleware '{$service}' priority must be numeric.");
        }

        return new self($service, (int) $priority);
    }
}
