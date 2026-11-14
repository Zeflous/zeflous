<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Anti-regression zero-dependency gate (CONTRIBUTING.md rules 1 and 16).
 *
 * ZEF ships zero Composer runtime packages: `composer.json` `require` must
 * be exactly the platform block `{"php": "^8.4", "ext-mbstring": "*"}` and
 * `composer.lock` production `packages` must stay empty. The platform block
 * is the environment contract, not a dependency: the PHP version floor the
 * engine rides and the mbstring extension the runtime's own multibyte
 * grammar needs (issue #774: ~330 `mb_*` call sites rode an extension the
 * manifest never declared -- a fresh install on a build without it fated
 * the first request, the worst failure class there is; declared, the
 * refusal moves to `composer install`, where the operator can read it).
 * This gate fails the build when either invariant is violated, so a
 * runtime dependency can never be introduced silently -- and neither can
 * the extension contract quietly narrow back to undeclared.
 *
 * The manifest pair is the gate's whole input ({@see ComposerManifest}): the
 * dev channels pass by construction because the input type carries no field
 * that could read them, and every dependency source outside the Composer
 * manifests -- a committed vendor tree, a bundled artifact -- is outside the
 * gate by design. The scope corpus (issue #576,
 * {@see ZeroDependencyScopeCorpusTest}) pins that fence so widening it stays
 * an owner decision rather than a quiet drift.
 */
final readonly class ZeroDependencyGate
{
    /** The exact production platform block the framework allows. */
    private const array EXPECTED_REQUIRE = ['php' => '^8.4', 'ext-mbstring' => '*'];

    public function evaluate(ComposerManifest $composerManifest): GateResult
    {
        $failures = [];

        if ($composerManifest->require !== self::EXPECTED_REQUIRE) {
            $encoded = json_encode($composerManifest->require, \JSON_UNESCAPED_SLASHES);

            $failures[] = \sprintf(
                'composer.json require is %s, expected exactly {"php": "^8.4", "ext-mbstring": "*"}',
                \is_string($encoded) ? $encoded : '<unencodable>',
            );
        }

        if ($composerManifest->packages !== []) {
            $failures[] = \sprintf(
                'composer.lock declares %d production package(s); the packages array must stay empty',
                \count($composerManifest->packages),
            );
        }

        if ($failures === []) {
            return GateResult::passed(
                'Zero-dependency gate PASSED: composer.json require is {"php": "^8.4", "ext-mbstring": "*"}'
                . ' and composer.lock packages is [].',
            );
        }

        return GateResult::failed('Zero-dependency gate FAILED: ' . implode('; ', $failures) . '.');
    }
}
