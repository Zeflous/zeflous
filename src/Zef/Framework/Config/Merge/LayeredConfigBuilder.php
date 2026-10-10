<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Merge;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\Loader\ConfigLoader;

/**
 * Builds the frozen runtime repository from the three configuration
 * layers -- Default < App < Env, later layers win (see `ROADMAP.md`,
 * "Configuration System + DSL + Radix Tree") -- in one {@see ArrayMerger}
 * pass that produces a single immutable {@see Config} the kernel can share
 * across a persistent worker's requests.
 *
 * The builder follows the copy-on-write `with*` protocol (CONTRIBUTING.md
 * rule 3): every `with*` method returns a *new* builder and replaces --
 * never appends to -- the layer it addresses, the same replace semantics
 * `Config::withSet()` applies to a value. Declaring a layer twice keeps
 * only the later declaration, which keeps the build deterministic and the
 * builder itself immutable.
 *
 * The file-backed `with*` variants are the director's first in-repo
 * consumer ({@see ConfigLoader::file()} / {@see ConfigLoader::directory()}):
 * the defaults and app layers load straight from allowlisted PHP
 * configuration sources, optionally nested under a dot-notation prefix,
 * and every fail-closed loader guarantee (the basename allowlist, the
 * `realpath()` canonicalisation, the array return contract) propagates
 * through the builder unchanged. The environment layer arrives as an
 * {@see EnvSource} -- prefix-scoped, raw string values -- because
 * environments are read, not authored.
 *
 * The builder is the configuration layer's composition seam: the kernel
 * bootstrapper wires it behind schema validation and the compiled cache,
 * and host applications may compose it directly, which is why it is marked
 * `@api` rather than being baselined or suppressed.
 *
 * @api
 */
final readonly class LayeredConfigBuilder
{
    /**
     * @param array<array-key, mixed> $defaults
     * @param array<array-key, mixed> $app
     * @param array<array-key, mixed> $env
     */
    private function __construct(private array $defaults, private array $app, private array $env)
    {
    }

    /**
     * Starts an empty builder: no defaults, no app overrides, no
     * environment, whose repository is the empty configuration.
     */
    public static function create(): self
    {
        return new self([], [], []);
    }

    /**
     * Replaces the defaults layer (the lowest precedence).
     *
     * @param array<array-key, mixed> $defaults
     */
    public function withDefaults(array $defaults): self
    {
        return new self($defaults, $this->app, $this->env);
    }

    /**
     * Loads the defaults layer from a single PHP configuration file,
     * optionally nested under a prefix.
     *
     * @param non-empty-list<non-empty-string> $allowedFiles
     */
    public function withDefaultFile(string $path, array $allowedFiles, string $prefix = ''): self
    {
        return $this->withDefaults(ConfigLoader::file($path, $allowedFiles, $prefix));
    }

    /**
     * Loads the defaults layer from a directory of PHP configuration
     * files, optionally nested under a prefix. Every direct `*.php` child
     * must be listed in the allowlist.
     *
     * @param non-empty-list<non-empty-string> $allowedFiles
     */
    public function withDefaultDirectory(string $path, array $allowedFiles, string $prefix = ''): self
    {
        return $this->withDefaults(ConfigLoader::directory($path, $allowedFiles, $prefix));
    }

    /**
     * Replaces the app layer (the middle precedence).
     *
     * @param array<array-key, mixed> $app
     */
    public function withApp(array $app): self
    {
        return new self($this->defaults, $app, $this->env);
    }

    /**
     * Loads the app layer from a single PHP configuration file, optionally
     * nested under a prefix.
     *
     * @param non-empty-list<non-empty-string> $allowedFiles
     */
    public function withAppFile(string $path, array $allowedFiles, string $prefix = ''): self
    {
        return $this->withApp(ConfigLoader::file($path, $allowedFiles, $prefix));
    }

    /**
     * Loads the app layer from a directory of PHP configuration files,
     * optionally nested under a prefix. Every direct `*.php` child must be
     * listed in the allowlist.
     *
     * @param non-empty-list<non-empty-string> $allowedFiles
     */
    public function withAppDirectory(string $path, array $allowedFiles, string $prefix = ''): self
    {
        return $this->withApp(ConfigLoader::directory($path, $allowedFiles, $prefix));
    }

    /**
     * Replaces the environment layer (the highest precedence) with the
     * source's folded variables.
     */
    public function withEnvironment(EnvSource $envSource): self
    {
        return new self($this->defaults, $this->app, $envSource->toArray());
    }

    /**
     * Merges Default < App < Env into a new frozen repository. Every call
     * yields a distinct, equal repository -- the layers are re-merged and
     * nothing is cached, in line with the no-mtime decision the compiled
     * cache will own.
     */
    public function build(): Config
    {
        return new Config(ArrayMerger::merge($this->defaults, $this->app, $this->env));
    }
}
