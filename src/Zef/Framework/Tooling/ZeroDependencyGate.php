<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Anti-regression zero-dependency gate (CONTRIBUTING.md rules 1 and 15).
 *
 * ZEF ships zero Composer runtime dependencies: `composer.json` `require` must
 * be exactly `{"php": "^8.4"}` and `composer.lock` production `packages` must
 * stay empty. This gate fails the build when either invariant is violated, so
 * a runtime dependency can never be introduced silently.
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
