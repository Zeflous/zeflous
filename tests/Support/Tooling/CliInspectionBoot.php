<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Kernel;

/**
 * The CLI inspection commands' boot: the one seam `bin/zef list` and the
 * declared-command dispatch share -- a configuration directory becomes
 * the booted kernel those commands answer from, booted through the same
 * application orchestrator the runtime rides ({@see
 * ApplicationBootstrapper}, the route and migration commands' own
 * doctrine: never a parallel parse -- the layered, validated,
 * environment-resolved repository is the one the boot answers, and the
 * module roster's joined `console.commands` table surfaces as the
 * boot's own typed refusal).
 *
 * The kernel answers whole because the two consumers need different
 * halves of it: the directory renders the frozen configuration's joined
 * command table, and the dispatch resolves the one handler the
 * operator's verb declared through the booted container -- the same
 * one-call boot, never a second composition.
 *
 * The one seam the boot owns is the container AOT artifact: the host's
 * real boot wires their own cache location, which a CLI cannot know,
 * so the boot wires the throwaway file the analysis CLIs share
 * ({@see ContainerAnalysisArtifact}) and never lets it outlive the
 * boot.
 */
final readonly class CliInspectionBoot
{
    /**
     * Boots the configuration directory's kernel -- the composition the
     * directory renders from and the dispatch resolves through.
     *
     * @param non-empty-string $configDirectory the host's configuration
     *                                          directory (the `app/configs` layout)
     */
    public static function kernel(string $configDirectory): Kernel
    {
        $artifactFile = ContainerAnalysisArtifact::path();

        try {
            return new ApplicationBootstrapper(
                containerAotCache: new ContainerAotCache($artifactFile),
            )->boot($configDirectory);
        } finally {
            if (is_file($artifactFile)) {
                unlink($artifactFile);
            }
        }
    }
}
