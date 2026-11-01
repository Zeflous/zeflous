<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * One module's normalized declaration: the class that implements the
 * contract, the identity it answers, and the absolute address of its
 * configuration schema when a manifest declared one.
 *
 * The declaration is the unit the whole engine passes around -- the
 * discovery fold builds it (from a class-string entry or a parsed
 * manifest), the ordering fold sorts it, the registration and boot phases
 * run it. The schema path is resolved to its absolute spelling by the
 * discovery fold, because only that fold knows the file the manifest came
 * from; a class-string entry carries no schema of its own, which is what
 * makes the "a block without a schema is refused" posture enforceable.
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
        return new self($this->class, $this->metadata, $configSchema, $this->routes);
    }

    /**
     * Answers the declaration with the routes path resolved to its absolute
     * spelling -- the same discovery refinement the schema path rides, for
     * the manifest's optional route-extension declaration.
     */
    public function withRoutes(?string $routes): self
    {
        return new self($this->class, $this->metadata, $this->configSchema, $routes);
    }
}
