<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The order the workflow map answers in.
 *
 * The collector's walk asks the filesystem which files exist -- an answer
 * whose iteration order the filesystem owns (DirectoryIterator answers
 * readdir, and a filesystem may answer it already alphabetised) -- while
 * this fold asks in what order the audit reads the files back: an answer
 * the vocabulary owns, that a test pins by handing the fold an unsorted
 * map directly. The two questions are two seams because a sort observed
 * only through the walk is only killed on filesystems whose readdir
 * order happens to disagree with the sorted order -- an
 * environment-dependent kill the mutation lane reddened main with.
 */
final class SortedWorkflowMap
{
    /**
     * @param array<string, string> $workflows map of file name => file contents
     *
     * @return array<string, string> the same map, sorted by file name
     */
    public static function over(array $workflows): array
    {
        ksort($workflows);

        return $workflows;
    }
}
