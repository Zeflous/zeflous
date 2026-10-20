<?php

declare(strict_types=1);

namespace Zef\Framework;

/**
 * The container's environment doctrine: the one verdict that adjusts the
 * boot's cache posture and the lane's error reporting to the environment
 * the framework runs in (ROADMAP, "CONTAINER" area 11 -- "Mode
 * development vs production: Cache & pelaporan error disesuaikan
 * lingkungan").
 *
 * The doctrine mirrors the error lane's own ({@see Middleware\DebugToolbarFactory}
 * arms the debug toolbar through it): a configuration knob plus the
 * `APP_ENV` allowlist, the fold shared through {@see AppEnvironment}.
 * The vocabulary is re-declared on the container side rather than
 * shared through the middleware seam because the layers keep it
 * unreachable -- the container lane owns no HTTP dependency and the boot
 * asks its question before any middleware exists -- the same
 * re-declaration trade the AOT toggles carry ({@see Container\Aot\AotToggles},
 * whose KDD records the decision).
 *
 * The knob's grammar is one key: `container.mode`, spelling one of
 * {@see ContainerModeRefusals::SPELLINGS} (the default when the knob is
 * absent, null or an empty map -- the framework ships environment-bound,
 * never mode-bound). `auto` is the honest default because the environment
 * is the deployment's own declaration: the shared `APP_ENV` allowlist
 * fold answers development, and every other value -- including an unset
 * one -- answers production, the fail-closed side of the fold. The
 * grammar itself is the spellings unit's own ({@see ContainerModeSpellings},
 * the AOT lane's pair idiom), the refusals the words unit's
 * ({@see ContainerModeRefusals}) -- this unit is the thin verdict shell,
 * exactly the shape {@see Container\Aot\AotMap} keeps.
 *
 * The verdict is resolved at parse time, never deferred: the lanes ask
 * {@see isDevelopment()} / {@see isProduction()} as plain booleans, and
 * the boot folds `auto` exactly once -- the same single-read discipline
 * the toolbar factory's own fold carries.
 */
final readonly class ContainerMode
{
    private function __construct(
        private bool $development,
    ) {
    }

    /**
     * Parses the `container` knob block into its mode verdict.
     *
     * @param string $key    the knob block's own name, for the refusals
     * @param mixed  $source the raw `container` value from the configuration
     */
    public static function parse(string $key, mixed $source): self
    {
        return new self(ContainerModeSpellings::verdict($key, $source));
    }

    /**
     * Whether the boot runs in development posture: the fresh compile is
     * the source of truth and the artifact lane never writes.
     */
    public function isDevelopment(): bool
    {
        return $this->development;
    }

    /**
     * Whether the boot runs in production posture: the compile/dump lane
     * may lay artifacts down and the fast path is the expected boot.
     */
    public function isProduction(): bool
    {
        return !$this->development;
    }
}
