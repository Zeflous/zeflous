<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\ServiceTags;

/**
 * One `tags.interfaces` value's verdict: the tag a rule hands the
 * implementations of its interface (see {@see AutoconfigInterfaces}
 * for the map these serve).
 *
 * The value must be a string the tags grammar accepts -- the same
 * vocabulary every declared tag must match, so a rule can never hand
 * a declaration a tag the declaration itself could not spell; a
 * refused spelling is surfaced with the rule's own interface named
 * and the grammar's message carried through.
 */
final readonly class AutoconfigTagRule
{
    /**
     * @param int|string $interface the rule's own key, named in the refusals
     * @param mixed      $tag       the raw map value
     */
    public static function validated(string $key, int|string $interface, mixed $tag): string
    {
        if (!\is_string($tag)) {
            throw AutoconfigRefusals::tagNotString($key, $interface, $tag);
        }

        try {
            ServiceTags::assert($tag);
        } catch (DefinitionException $definitionException) {
            throw AutoconfigRefusals::tagRefused($key, $interface, $definitionException);
        }

        return $tag;
    }
}
