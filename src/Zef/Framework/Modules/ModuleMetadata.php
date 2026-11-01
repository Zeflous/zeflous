<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The module's declarative identity: the name, the version and the required
 * module names.
 *
 * Every spelling is validated at construction -- a malformed name or
 * version, a duplicated requirement or a self-requirement is a
 * construction-time refusal, so no malformed identity can ride through the
 * engine into an order fold or a manifest comparison. The identity is
 * answered by the module's own `metadata()` and, for the manifest spelling,
 * parsed from the module's `module.php`; when both are present the boot
 * refuses a disagreement between them.
 */
final readonly class ModuleMetadata
{
    /** @var list<string> */
    public array $requires;

    /**
     * @param array<array-key, mixed> $requires the required module names,
     *                                          each a `vendor/name`
     *                                          spelling
     *
     * @throws ModuleException when any spelling breaks its grammar
     */
    public function __construct(
        public string $name,
        public string $version,
        array $requires = [],
    ) {
        ModuleName::asserted($this->name);
        ModuleVersion::asserted($this->version);
        $this->requires = ModuleRequires::validated($requires, $this->name);
    }
}
