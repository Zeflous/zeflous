<?php

declare(strict_types=1);

namespace Zef\Framework;

/**
 * The mode knob's grammar fold: the whole `container` block parsed
 * into its development verdict -- the AOT lane's own pair idiom
 * ({@see Container\Aot\AotToggles} folds the `aot`
 * knob's toggles the same way), so the verdict unit
 * ({@see ContainerMode}) stays the thin shell and this unit keeps
 * the grammar: the empty-knob fold, the map shape guard, the
 * unknown-key guard, the mode's own type guard, and the spelling
 * match over the closed vocabulary, every refusal the words unit's
 * own ({@see ContainerModeRefusals}).
 */
final readonly class ContainerModeSpellings
{
    /**
     * The knob block's development verdict.
     *
     * @param string $key    the knob block's own name, for the refusals
     * @param mixed  $source the raw `container` value from the configuration
     */
    public static function verdict(string $key, mixed $source): bool
    {
        if (!\is_array($source)) {
            if ($source === null) {
                return AppEnvironment::isDevelopment();
            }

            throw ContainerModeRefusals::notAMap($key, $source);
        }

        $unknown = array_values(array_diff(array_keys($source), ['mode']));

        if ($unknown !== []) {
            throw ContainerModeRefusals::unknownKeys($key, $unknown);
        }

        $mode = $source['mode'] ?? null;

        if ($mode === null) {
            return AppEnvironment::isDevelopment();
        }

        if (!\is_string($mode)) {
            throw ContainerModeRefusals::modeNotAString($key, $mode);
        }

        return match (mb_strtolower($mode)) {
            'development' => true,
            'production' => false,
            'auto' => AppEnvironment::isDevelopment(),
            default => throw ContainerModeRefusals::unknownSpelling($key, $mode),
        };
    }
}
