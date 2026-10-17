<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The closed vocabulary of a definition's sharing policy -- how many
 * instances a service's declarations may ever observe (ROADMAP,
 * "CONTAINER" area 5: Scope & Lifecycle).
 *
 * The vocabulary IS the policy, one word per lane, with no second
 * `shared` knob beside it: the reference lineage's `shared` flag could
 * only ever legally combine with the singleton spelling (its own
 * constructor refuses every other combination), so the flag said
 * nothing the lifetime did not already say -- the knob that must stay
 * live is the knob that changes something, and this one never could.
 * A transient spelling is the honest name for "built per resolution,
 * never memoised"; the unshared singleton it would have spelled is the
 * same lane under two words.
 *
 * The word order is a capture order, useful when the graph judges what
 * may depend on what: a singleton may be captured by anyone forever, a
 * request-scoped instance only within its own scope, a transient only
 * at the moment of its construction.
 */
enum ServiceLifetime: string
{
    /** One instance for the container's whole life; the default, and the behaviour every earlier declaration already had. */
    case SINGLETON = 'singleton';

    /** One instance per request scope, memoised inside it, refused outside one. */
    case REQUEST = 'request';

    /** A fresh instance per resolution; never memoised anywhere. */
    case TRANSIENT = 'transient';

    /**
     * Parses the grammar's `lifetime` key for one service.
     *
     * An absent key answers null -- the caller's default applies -- while
     * a present key must be one of this vocabulary's own spellings: a
     * non-string or an unknown word is the declaration defect it is,
     * refused with the service's identifier and the accepted spellings.
     *
     * @param mixed $key the raw `lifetime` value from the definition map
     */
    public static function fromKey(string $id, mixed $key): ?self
    {
        if ($key === null) {
            return null;
        }

        if (!\is_string($key)) {
            throw new DefinitionException(
                "Service '{$id}' lifetime must be one of " . self::spelled() . ', ' . get_debug_type($key) . ' given.',
            );
        }

        return self::tryFrom($key)
            ?? throw new DefinitionException(
                "Service '{$id}' carries the unknown lifetime '{$key}' (one of " . self::spelled() . ' is required).',
            );
    }

    /**
     * The accepted spellings, quoted for a refusal message.
     */
    private static function spelled(): string
    {
        return "'singleton', 'request', 'transient'";
    }
}
