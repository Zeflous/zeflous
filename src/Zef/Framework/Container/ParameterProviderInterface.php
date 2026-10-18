<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * A parameter whose value is computed at the boot -- the compile --
 * phase (ROADMAP, container area 1, "Parameter providers: nilai
 * dihitung/di-resolve saat fase kompilasi").
 *
 * Declared under the reserved `providers` key of the `parameters`
 * configuration:
 *
 *     'parameters' => [
 *         'providers' => ['app.version' => VersionProvider::class],
 *         'db.host' => 'localhost',
 *     ]
 *
 * The class is constructed with no arguments and its
 * {@see self::value()} answer becomes the parameter's value -- a
 * scalar, null or an array of such, never an object, exactly the
 * injectable surface a literal parameter may carry. The answer is
 * computed once per boot, at parse, on the record: the refusal that
 * names the provider and the address is raised before a single
 * definition is installed.
 *
 * The provider is the host's own computation seam (a version read from
 * a lockfile, a directory resolved from the installation layout) --
 * the one place the parameter namespace grows values that are not
 * spelled data. It is deliberately NOT the service-provider machinery
 * the reference containers carry ({@see Contracts\ServiceProviderInterface}):
 * that lane belongs to the module-integration campaign, where a
 * provider composes service definitions rather than answering one
 * parameter's value.
 */
interface ParameterProviderInterface
{
    /**
     * The parameter's value, computed at the compile phase.
     *
     * @return mixed a scalar, null, or an array of such
     */
    public function value(): mixed;
}
