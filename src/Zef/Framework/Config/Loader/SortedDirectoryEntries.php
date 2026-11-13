<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Loader;

use SplFileInfo;

/**
 * The directory scan's name-order fold: one question -- in what order do
 * the directory's collected entries walk?
 *
 * The filesystem's own readdir order is host-owned (a hidden entry can
 * answer first or last, and an alphabetising filesystem answers
 * differently than a hash-ordered one), so {@see DirectoryLoader} hands
 * the entries it collected to this fold and walks the sorted answer:
 * every skip, delegation and refusal downstream then observes the same
 * entry order on every host, and a hidden entry always sorts before the
 * loadable children behind it -- which is what makes the skip's
 * observation deterministic.
 *
 * The fold is pure over the injected list (never touching the
 * filesystem itself) so its own contract is testable without a host:
 * the sorted answer is the only behaviour it owns. The sort is a plain
 * byte-wise string sort over the filenames -- the entries of one
 * directory never share a name, so keying by name loses nothing, and
 * the answer is the same on every host and every locale.
 *
 * @internal
 */
final class SortedDirectoryEntries
{
    /**
     * Answers the entries in sorted name order.
     *
     * @param list<SplFileInfo> $entries the directory's collected children
     *
     * @return list<SplFileInfo> the same entries, ordered by filename
     */
    public static function inNameOrder(array $entries): array
    {
        $byName = [];

        foreach ($entries as $entry) {
            $byName[$entry->getFilename()] = $entry;
        }

        ksort($byName, \SORT_STRING);

        return array_values($byName);
    }
}
