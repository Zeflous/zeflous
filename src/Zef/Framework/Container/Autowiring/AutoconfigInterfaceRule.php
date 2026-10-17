<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * One `tags.interfaces` entry's verdict, composed of its two halves:
 * the {@see AutoconfigInterfaceName} the rule is declared for and the
 * {@see AutoconfigTagRule} tag it hands that interface's
 * implementations (see {@see AutoconfigInterfaces} for the map these
 * walk).
 *
 * The pair is answered in declaration order -- the name first, so a
 * malformed key refuses before its value is ever judged.
 */
final readonly class AutoconfigInterfaceRule
{
    /**
     * @param int|string $interface the raw map key
     * @param mixed      $tag       the raw map value
     *
     * @return array{class-string, string} the validated interface => tag pair
     */
    public static function validated(string $key, int|string $interface, mixed $tag): array
    {
        $name = AutoconfigInterfaceName::validated($key, $interface);

        return [$name, AutoconfigTagRule::validated($key, $name, $tag)];
    }
}
