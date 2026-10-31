<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The module's readonly window on the boot: its identity and its own
 * namespaced, schema-validated configuration block.
 *
 * The context a module's `boot()` receives carries no framework internals
 * and no superglobal -- the hexagonal posture the roadmap spells -- only
 * the block the module's own name derived (the `zef.payment`-shaped key)
 * after the engine asserted it against the module's declared schema. A
 * module reads its configuration through the block's array access or
 * through the `has()`/`get()` conveniences; nothing here can reach
 * another module's block or the host's own keys.
 *
 * The context is also the extension-point carrier: the faces the later
 * module slices open (routes, events, the command registry) join this
 * object as accessors, so the contract's `boot()` signature never breaks
 * while the surface grows.
 *
 * @api the module author's own read surface -- the contract's `boot()`
 *      hands this object over, so its members are consumed from module
 *      code that lives outside this repository
 */
final readonly class ModuleContext
{
    /**
     * @param array<string, mixed> $config the module's own validated block
     */
    public function __construct(
        public string $name,
        public string $version,
        public array $config,
    ) {
    }

    /**
     * Whether the module's block carries the key.
     */
    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->config);
    }

    /**
     * Answers the key's value from the module's block, or the default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
