<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Zef\Framework\Container\Container;
use Zef\Framework\Container\FactoryClosures;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\ServiceTags;

/**
 * The artifact's install half: the typed entries one validated
 * {@see AotArtifact} carries, installed into the container through its
 * existing public surface -- the same {@see Container::set()} the full
 * lane's install loop uses, so the memoisation, the sharing semantics
 * and the resolution machinery are the same machinery, not a parallel
 * copy of them.
 *
 * An {@see AotClosureEntry} installs its live closure verbatim: the
 * artifact's `require` already evaluated the rendered code, so nothing
 * between the file and the store runs but the registration. An
 * {@see AotBuildEntry} is reconstructed once -- the factory declaration
 * through the same {@see FactoryClosures} builder the full lane
 * dispatches to, with empty tags because the baked tag index, not the
 * per-definition tags, answers the read side on this lane -- and the
 * built closure installs the same way.
 *
 * The declaration order is the artifact's own (the dump wrote the
 * pipeline's order), so a same-identifier re-declaration keeps the
 * full lane's last-wins answer on this lane too.
 */
final readonly class AotDefinitions
{
    /**
     * Installs a validated artifact's entries into the container.
     *
     * @return int how many definitions were installed
     */
    public static function install(Container $container, AotArtifact $aotArtifact): int
    {
        $installed = 0;

        foreach ($aotArtifact->entries() as $id => $entry) {
            $container->set(
                $id,
                $entry instanceof AotClosureEntry
                    ? $entry->closure
                    : FactoryClosures::build(self::definition($id, $entry)),
                $entry->lifetime,
            );

            ++$installed;
        }

        return $installed;
    }

    /**
     * The build entry's reconstruction: the factory declaration as the
     * typed data the artifact carried.
     */
    private static function definition(string $id, AotBuildEntry $aotBuildEntry): FactoryDefinition
    {
        return new FactoryDefinition(
            $id,
            $aotBuildEntry->factory,
            $aotBuildEntry->arguments,
            $aotBuildEntry->lifetime,
            new ServiceTags(),
            $aotBuildEntry->calls,
        );
    }
}
