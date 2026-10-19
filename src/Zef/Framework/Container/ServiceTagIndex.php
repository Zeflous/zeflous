<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The boot-built index of a definition set's tags: which service
 * identifiers carry which tag (the tags & grouping lane's read side
 * under the locator; ROADMAP, "CONTAINER" area 6).
 *
 * The index is built once, from the planned definition set the
 * bootstrapper installs -- never consulted incrementally, never
 * rebuilt at runtime. Its two passes spell the sharing semantics the
 * container's own install loop already carries: a re-declared
 * identifier (the framework's registration rebound by the host's
 * same-identifier entry) contributes the LAST declaration's tags,
 * while the identifier keeps its first-declaration position -- the
 * same identifier installs twice through {@see Container::set()} and
 * the second call wins, so the index may not invent a third reading.
 *
 * Tag order follows declaration order, and a tag's identifiers follow
 * first-declaration order: the answer is stable for a given definition
 * set, the way a given set of providers is stable at boot. The AOT
 * load lane reads the same answer from the index the artifact baked
 * ({@see fromIdsByTag()}), so both lanes answer from the same shape.
 */
final readonly class ServiceTagIndex
{
    /**
     * @param array<string, list<string>> $idsByTag the walked or baked index
     */
    private function __construct(private array $idsByTag)
    {
    }

    /**
     * The index walked out of a planned definition set.
     *
     * @param list<ServiceDefinition> $definitions the planned definition
     *                                             set, in declaration order
     */
    public static function fromDefinitions(array $definitions): self
    {
        $tagsById = [];
        foreach ($definitions as $definition) {
            $tagsById[$definition->id] = $definition->tags->all();
        }

        $idsByTag = [];
        foreach ($tagsById as $id => $tags) {
            foreach ($tags as $tag) {
                $idsByTag[$tag][] = $id;
            }
        }

        return new self($idsByTag);
    }

    /**
     * The index the AOT artifact baked at dump time, read back as the
     * same answer the definition walk would have built -- the load
     * lane's skip over the walk, with the shape the walk produces.
     *
     * @param array<string, list<string>> $idsByTag the baked index
     */
    public static function fromIdsByTag(array $idsByTag): self
    {
        return new self($idsByTag);
    }

    /**
     * The identifiers carrying one tag, in first-declaration order --
     * an empty list when nothing carries it.
     *
     * @return list<string>
     */
    public function ids(string $tag): array
    {
        return $this->idsByTag[$tag] ?? [];
    }

    /**
     * The index as the data the AOT artifact bakes: the whole map, the
     * shape {@see fromIdsByTag()} reads back.
     *
     * @return array<string, list<string>>
     */
    public function toArray(): array
    {
        return $this->idsByTag;
    }
}
