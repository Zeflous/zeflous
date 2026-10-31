<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The module version grammar: the constraint spellings a manifest or a
 * `metadata()` answer may carry -- the `^1.0` shape the roadmap's own
 * manifest sketch uses.
 *
 * A version is an optional constraint operator (`^`, `~`, `>=`, `>`, `<=`,
 * `<`, `=`), then two or three dot-separated numbers, then an optional
 * pre-release or build suffix (`-alpha.1`, `+build.7`), under a 32-byte
 * ceiling. The engine does not resolve constraints against one another --
 * the version is identity metadata the inspector and the manifests spell,
 * not a solver -- so the grammar's whole job is to keep one canonical
 * shape in every record that carries one.
 */
final readonly class ModuleVersion
{
    private const string PATTERN
        = '/^(?:\^|~|>=|>|<=|<|=)?\d+(?:\.\d+){1,2}(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/D';

    private const int CEILING = 32;

    /**
     * @throws ModuleException when the spelling breaks the grammar
     */
    public function __construct(
        public string $version,
    ) {
        if (mb_strlen($this->version) > self::CEILING || preg_match(self::PATTERN, $this->version) !== 1) {
            throw ModuleExceptionSpelling::versionGrammar($this->version);
        }
    }
}
