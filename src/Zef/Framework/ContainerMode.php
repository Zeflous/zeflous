<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Container\DefinitionException;

/**
 * The container's environment doctrine: the one verdict that adjusts the
 * boot's cache posture and the lane's error reporting to the environment
 * the framework runs in (ROADMAP, "CONTAINER" area 11 -- "Mode
 * development vs production: Cache & pelaporan error disesuaikan
 * lingkungan").
 *
 * The doctrine mirrors the error lane's own ({@see Middleware\DebugToolbarFactory}
 * arms the debug toolbar through it): a configuration knob plus the
 * `APP_ENV` allowlist. The vocabulary is re-declared here rather than
 * shared through the middleware seam because the layers keep it
 * unreachable -- the container lane owns no HTTP dependency and the boot
 * asks its question before any middleware exists -- the same
 * re-declaration trade the AOT toggles carry ({@see Container\Aot\AotToggles},
 * whose KDD records the decision). The spellings fold through the
 * MBString case map, value-equivalent by construction: the only
 * single-character lowercase landing in ASCII the case map carries is
 * U+212A to `k`, and no vocabulary word contains a `k`.
 *
 * The knob's grammar is one key: `container.mode`, spelling one of
 * `development`, `production` or `auto` (the default when the knob is
 * absent, null or an empty map -- the framework ships environment-bound,
 * never mode-bound). `auto` is the honest default because the environment
 * is the deployment's own declaration: `APP_ENV` carrying any of
 * `local`, `development`, `dev`, `test` or `testing` (case-insensitive)
 * answers development, and every other value -- including an unset one --
 * answers production, the fail-closed side of the fold. An unknown
 * spelling or a non-string value refuses with the knob named and the
 * known list spelled ({@see DefinitionException}, the family every
 * sibling knob refuses through).
 *
 * The verdict is resolved at parse time, never deferred: the lanes ask
 * {@see isDevelopment()} / {@see isProduction()} as plain booleans, and
 * the boot folds `auto` exactly once -- the same single-read discipline
 * the toolbar factory's own fold carries.
 */
final readonly class ContainerMode
{
    /**
     * The `APP_ENV` spellings that answer development under `auto`:
     * the toolbar doctrine's own allowlist, re-declared (see the class
     * docblock for the trade).
     */
    private const array DEVELOPMENT_ENVIRONMENTS = ['local', 'development', 'dev', 'test', 'testing'];

    /**
     * The knob's closed vocabulary, comparison form: the two modes and
     * the environment-derived default.
     */
    private const array SPELLINGS = ['development', 'production', 'auto'];

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
        if ($source === null || $source === []) {
            return self::auto();
        }

        if (!\is_array($source)) {
            throw self::notAMap($key, $source);
        }

        $unknown = [];
        foreach (array_keys($source) as $name) {
            if ($name === 'mode') {
                continue;
            }

            $unknown[] = (string) $name;
        }

        if ($unknown !== []) {
            throw self::unknownKeys($key, $unknown);
        }

        $mode = $source['mode'] ?? null;

        if ($mode === null) {
            return self::auto();
        }

        if (!\is_string($mode)) {
            throw self::modeNotAString($key, $mode);
        }

        return match (mb_strtolower($mode)) {
            'development' => new self(true),
            'production' => new self(false),
            'auto' => self::auto(),
            default => throw self::unknownSpelling($key, $mode),
        };
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

    /**
     * The environment-derived verdict: `APP_ENV`'s allowlist answers
     * development, and everything else -- including an unset variable --
     * answers production, the fail-closed side of the fold.
     */
    private static function auto(): self
    {
        $environment = getenv('APP_ENV');

        return new self(
            \is_string($environment)
                && \in_array(mb_strtolower($environment), self::DEVELOPMENT_ENVIRONMENTS, true),
        );
    }

    private static function notAMap(string $key, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration must be a map of container keys, "
            . get_debug_type($source) . ' given.',
        );
    }

    /**
     * @param list<string> $unknown
     */
    private static function unknownKeys(string $key, array $unknown): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration carries the unknown key '" . implode("', '", $unknown)
            . "' (known keys: mode).",
        );
    }

    private static function modeNotAString(string $key, mixed $mode): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.mode' configuration must be a string, "
            . get_debug_type($mode) . ' given.',
        );
    }

    private static function unknownSpelling(string $key, string $mode): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.mode' configuration must be one of '" . implode("', '", self::SPELLINGS)
            . "' (case-insensitive); '{$mode}' given.",
        );
    }
}
