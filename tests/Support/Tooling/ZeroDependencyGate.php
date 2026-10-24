<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Anti-regression zero-dependency gate (CONTRIBUTING.md rules 1 and 16).
 *
 * ZEF ships zero Composer runtime dependencies: `composer.json` `require` must
 * be exactly `{"php": "^8.4"}` and `composer.lock` production `packages` must
 * stay empty. This gate fails the build when either invariant is violated, so
 * a runtime dependency can never be introduced silently.
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
    /** The exact production requirement block the framework allows. */
    private const array EXPECTED_REQUIRE = ['php' => '^8.4'];

    public function evaluate(ComposerManifest $composerManifest): GateResult
    {
        $failures = [];

        if ($composerManifest->require !== self::EXPECTED_REQUIRE) {
            $encoded = json_encode($composerManifest->require, \JSON_UNESCAPED_SLASHES);

            $failures[] = \sprintf(
                'composer.json require is %s, expected exactly {"php": "^8.4"}',
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
                'Zero-dependency gate PASSED: composer.json require is {"php": "^8.4"}'
                . ' and composer.lock packages is [].',
            );
        }

        return GateResult::failed('Zero-dependency gate FAILED: ' . implode('; ', $failures) . '.');
    }
}
