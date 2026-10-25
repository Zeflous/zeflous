<?php

declare(strict_types=1);

namespace Zef\Framework\Storage;

/**
 * The answered half of the `files/**` route map: the immutable
 * lane-to-address record {@see StorageLayout} walks to -- the root
 * every lane joins onto, one canonical path per lane, and the whole
 * map as the renderers and future config seams read it.
 *
 * The record is a resolved ANSWER, never a live view: it carries no
 * filesystem calls of its own, so a path it hands out is the path
 * the walk judged -- a tree that changes after the walk is a new
 * walk, and the caller who needs the fresh verdict takes one. The
 * spelling-keyed map ({@see self::lanes()}) is the shape the
 * inspector's JSON contract and any future knob derivation read;
 * the typed accessor ({@see self::path()}) is the shape src code
 * reads, so a typo'd lane name is an analyser finding, not a
 * runtime miss.
 */
final readonly class StoragePaths
{
    /**
     * @param string                $root  the resolved storage root every lane joins onto
     * @param array<string, string> $lanes the lane spellings to their canonical paths,
     *                                     the vocabulary's declaration order held
     */
    public function __construct(
        private string $root,
        private array $lanes,
    ) {
    }

    /**
     * The resolved storage root: the one directory every lane's
     * address in this record joins onto.
     */
    public function root(): string
    {
        return $this->root;
    }

    /**
     * One lane's canonical address.
     *
     * @param StorageLane $storageLane the lane whose directory is asked for
     *
     * @return string the resolved real path the walk judged for the lane
     */
    public function path(StorageLane $storageLane): string
    {
        return $this->lanes[$storageLane->spelling()];
    }

    /**
     * The whole map as the renderers read it: each lane's spelling
     * to its canonical path, the vocabulary's declaration order
     * held -- the shape the inspector's JSON contract spells and a
     * future config seam derives from.
     *
     * @return array<string, string> the lane spellings to their canonical paths
     */
    public function lanes(): array
    {
        return $this->lanes;
    }
}
