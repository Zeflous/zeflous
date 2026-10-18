<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The parameter address grammar (the registration-completion family's
 * shared vocabulary).
 *
 * A parameter address is a `.`-separated path whose segments name the
 * parameter's place in the `parameters` configuration map: `db.host`,
 * `session_driver`, `observability.export.timeout_ms`. One grammar
 * serves both spellings of an address -- the `#[Value]` attribute's key
 * ({@see Autowiring\Value}) and the `parameters` map's own keys -- so
 * the two can never disagree about what a well-formed address is.
 *
 * Segments match `[A-Za-z0-9_-]{1,64}`: letters, digits, underscores
 * and hyphens, deliberately the same character classes the tags lane's
 * grammar allows, and deliberately including the underscore because the
 * environment fold keeps single underscores inside their segment
 * (`APP_SESSION_DRIVER` folds to `session_driver`, so the
 * environment-addressable parameters are exactly the ones this grammar
 * admits). A colon is deliberately absent: the synthetic value service
 * identifier is `@value:` + the address, and an address carrying its
 * own colon would blur the prefix boundary the idiom depends on.
 *
 * The whole address is capped at 190 characters (the reference
 * container's own cap), and no segment may be empty -- `a..b` is
 * malformed exactly the way the configuration layer's own dot-key
 * mechanics refuse it, without this class growing a second opinion
 * about emptiness. The grammar owns its own refusals ({@see ServiceTags}
 * owns its the same way): a malformed address is refused here, with
 * the speller's context quoted, keeping the refusals vocabulary free
 * of a grammar dependency.
 */
final readonly class ParameterKeys
{
    /** The per-segment grammar: one address segment, at most 64 characters. */
    public const string SEGMENT = '[A-Za-z0-9_-]{1,64}';

    /** The whole-address length cap. */
    public const int MAX_LENGTH = 190;

    /**
     * Asserts one address against the grammar, refusing with the given
     * context (the speller -- the attribute or the map -- named
     * through it).
     */
    public static function assert(string $context, string $address): void
    {
        if ($address === '' || mb_strlen($address) > self::MAX_LENGTH) {
            throw new DefinitionException(
                "{$context} '{$address}' is invalid: an address is 1 to "
                . self::MAX_LENGTH . ' characters of dot-separated segments.',
            );
        }

        foreach (explode('.', $address) as $segment) {
            if (preg_match('/^' . self::SEGMENT . '$/', $segment) !== 1) {
                throw new DefinitionException(
                    "{$context} '{$address}' is invalid: the segment '{$segment}' must match "
                    . self::SEGMENT . ' (dot-separated; no colons -- the value-service '
                    . 'idiom owns the colon).',
                );
            }
        }
    }
}
