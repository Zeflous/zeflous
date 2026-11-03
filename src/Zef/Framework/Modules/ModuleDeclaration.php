<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * One module's normalized declaration: the class that implements the
 * contract, the identity it answers, and the absolute addresses of its
 * configuration schema and its extension-point files when a manifest
 * declared them.
 *
 * The declaration is the unit the whole engine passes around -- the
 * discovery fold builds it (from a class-string entry or a parsed
 * manifest), the ordering fold sorts it, the registration and boot phases
 * run it. The schema path is resolved to its absolute spelling by the
 * discovery fold, because only that fold knows the file the manifest came
 * from; a class-string entry carries no schema of its own, which is what
 * makes the "a block without a schema is refused" posture enforceable.
 * The `routes`, `events` and `cli` paths ride the same resolution: the
 * manifest's own optional extension-point declarations, consumed by the
 * boot's join folds -- not by the module's own phases.
 */
final readonly class ModuleDeclaration
{
    /**
     * @param string $class the qualified class name spelling the module
     *                      contract is answered through
     */
    public function __construct(
        public string $class,
        public ModuleMetadata $metadata,
        public ?string $configSchema = null,
        public ?string $routes = null,
        public ?string $events = null,
        public ?string $cli = null,
    ) {
        if ($this->class === '' || !str_contains($this->class, '\\')) {
            throw new ModuleException(
                "The module class spelling '{$this->class}' must be a qualified "
                . 'class name (the namespace separator is part of the spelling).',
            );
        }
    }

    /**
     * Answers the declaration with the schema path resolved to its absolute
     * spelling -- the discovery fold's own refinement after it read the
     * manifest the declaration came from.
     */
    public function withConfigSchema(?string $configSchema): self
    {
        return new self($this->class, $this->metadata, $configSchema, $this->routes, $this->events, $this->cli);
    }

    /**
     * Answers the declaration with the routes path resolved to its absolute
     * spelling -- the same discovery refinement the schema path rides, for
     * the manifest's optional route-extension declaration.
     */
    public function withRoutes(?string $routes): self
    {
        return new self($this->class, $this->metadata, $this->configSchema, $routes, $this->events, $this->cli);
    }

    /**
     * Answers the declaration with the events path resolved to its absolute
     * spelling -- the same discovery refinement the schema and routes paths
     * ride, for the manifest's optional event-extension declaration.
     */
    public function withEvents(?string $events): self
    {
        return new self($this->class, $this->metadata, $this->configSchema, $this->routes, $events, $this->cli);
    }

    /**
     * Answers the declaration with the cli path resolved to its absolute
     * spelling -- the same discovery refinement the schema, routes and
     * events paths ride, for the manifest's optional CLI-extension
     * declaration.
     */
    public function withCli(?string $cli): self
    {
        return new self($this->class, $this->metadata, $this->configSchema, $this->routes, $this->events, $cli);
    }
}
