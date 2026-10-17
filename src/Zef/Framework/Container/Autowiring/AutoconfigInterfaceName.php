<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * One `tags.interfaces` key's verdict: the interface name a rule is
 * declared for (see {@see AutoconfigInterfaces} for the map these
 * serve).
 *
 * The key must be a non-empty string naming an existing interface --
 * a rule the runtime could never match is a typo wearing a rule's
 * clothes, refused with the knob's own name and the key that failed.
 */
final readonly class AutoconfigInterfaceName
{
    /**
     * @param int|string $interface the raw map key
     *
     * @return class-string the interface the rule is declared for
     */
    public static function validated(string $key, int|string $interface): string
    {
        if (!\is_string($interface) || $interface === '') {
            throw AutoconfigRefusals::keyNotInterfaceName($key, $interface);
        }

        if (!interface_exists($interface)) {
            throw AutoconfigRefusals::missingInterface($key, $interface);
        }

        return $interface;
    }
}
