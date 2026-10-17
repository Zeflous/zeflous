<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The `tags.interfaces` value's own shape: the map of interface names
 * to the tags their implementations earn (see {@see AutoconfigMap}
 * for the knob this parses).
 *
 * A null value is the valid no-rules map; an empty one likewise; a
 * non-array or a list is the wrong shape, refused with the knob's own
 * name. Each entry's verdict belongs to
 * {@see AutoconfigInterfaceRule} -- this class only owns the map's
 * shape and the declaration order the rules keep.
 */
final readonly class AutoconfigInterfaces
{
    /**
     * @param mixed $interfaces the raw `tags.interfaces` value
     *
     * @return array<class-string, string>
     */
    public static function rules(string $key, mixed $interfaces): array
    {
        if ($interfaces === null || $interfaces === []) {
            return [];
        }

        if (!\is_array($interfaces)) {
            throw AutoconfigRefusals::interfacesNotAMap($key, $interfaces);
        }

        if (array_is_list($interfaces)) {
            throw AutoconfigRefusals::interfacesList($key);
        }

        $rules = [];
        foreach (array_keys($interfaces) as $interface) {
            $validated = AutoconfigInterfaceRule::validated($key, $interface, $interfaces[$interface]);
            $rules[$validated[0]] = $validated[1];
        }

        return $rules;
    }
}
