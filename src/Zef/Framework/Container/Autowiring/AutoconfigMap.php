<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\DefinitionKeys;

/**
 * The autoconfiguration knob's parsed form: the `tags` configuration
 * entry as validated rules (ROADMAP, "CONTAINER" area 3 -- the
 * marker-interface form of automatic tagging).
 *
 * The knob's grammar is deliberately one key: `tags.interfaces`, a map
 * of interface names to the tag every class-shaped declaration whose
 * class implements the interface earns at boot. An unknown key under
 * `tags` is refused with the known list (the configuration layer's own
 * style); the entries themselves are validated by
 * {@see AutoconfigInterfaces} and {@see AutoconfigInterfaceRule}.
 *
 * The empty knob -- absent, null, or an empty interfaces map -- is the
 * valid no-rules boot: the framework ships no marker rules of its own
 * ({@see \Zef\Framework\FrameworkDefaults} declares the empty map), a
 * host's rules arrive with the host's configuration.
 */
final readonly class AutoconfigMap
{
    /**
     * @param array<class-string, string> $interfaceTags
     */
    private function __construct(
        private array $interfaceTags,
    ) {
    }

    /**
     * The interface rules: one tag per interface, the map the boot
     * apply walks.
     *
     * @return array<class-string, string>
     */
    public function interfaceTags(): array
    {
        return $this->interfaceTags;
    }

    /**
     * Parses the `tags` configuration entry into its validated rules.
     *
     * @param mixed $source the raw `tags` value from the configuration
     */
    public static function parse(string $key, mixed $source): self
    {
        if ($source === null) {
            return new self([]);
        }

        if (!\is_array($source)) {
            throw AutoconfigRefusals::notAMap($key, $source);
        }

        $unknown = DefinitionKeys::unknownIn($source, ['interfaces']);
        if ($unknown !== null) {
            throw AutoconfigRefusals::unknownKey($key, $unknown);
        }

        return new self(AutoconfigInterfaces::rules($key, $source['interfaces'] ?? null));
    }
}
