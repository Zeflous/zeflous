<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The AOT knob's parsed form: the `aot` configuration entry as validated
 * toggles (ROADMAP, "CONTAINER" area 8 -- the compile/dump lane's own
 * switch, the one {@see \Zef\Framework\FrameworkDefaults} promised the
 * area would bring alive).
 *
 * The knob's grammar is two keys, both optional with the off default:
 * `aot.enabled` turns the compile/dump lane on (the boot consults the
 * container AOT cache instead of running the full reflection-based
 * pipeline), and `aot.warm` eagerly constructs every singleton once the
 * definitions are installed -- the warmup orchestration the scope &
 * lifecycle area deferred to this lane. An unknown key under `aot` is
 * refused with the known list (the configuration layer's own style, the
 * sibling knobs' idiom); both toggles fold through the closed vocabulary
 * ({@see AotToggles}) so an environment overlay can spell them.
 *
 * The empty knob -- absent, null, or an empty map -- is the valid
 * everything-off boot: {@see \Zef\Framework\FrameworkDefaults} declares
 * it that way, so the framework ships with the lane closed and a host
 * opts in from their own configuration.
 */
final readonly class AotMap
{
    private function __construct(
        private bool $enabled,
        private bool $warm,
    ) {
    }

    /**
     * Whether the compile/dump lane is on: the boot consults the
     * container AOT cache before running the full pipeline.
     */
    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Whether every singleton is eagerly constructed once the
     * definitions are installed.
     */
    public function warm(): bool
    {
        return $this->warm;
    }

    /**
     * Parses the `aot` configuration entry into its validated toggles.
     *
     * @param mixed $source the raw `aot` value from the configuration
     */
    public static function parse(string $key, mixed $source): self
    {
        if ($source === null) {
            return new self(false, false);
        }

        return new self(...AotToggles::pair($key, $source));
    }
}
