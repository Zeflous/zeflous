<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use DirectoryIterator;

/**
 * Collects the GitHub Actions workflow files that the concurrency gate audits.
 *
 * This is the only side of the workflow-concurrency report that touches the
 * filesystem, so the gate itself stays pure and fully unit-testable.
 *
 * Only the top level of `.github/workflows` is collected, mirroring GitHub's
 * own workflow discovery: nested directories are ignored by GitHub Actions,
 * so collecting them would audit files that never run. The walk is a plain
 * directory iteration with an extension filter -- deliberately NOT a glob
 * pattern, because a checkout root carrying glob metacharacters
 * (`/build/ci[2]/repo`) would make the pattern match nothing and the audit
 * would report its own absence as a pass. A file that cannot be read
 * refuses the whole collection ({@see ToolingException}): a workflow the
 * audit cannot see is a workflow whose concurrency misconfiguration
 * escapes the gate silently -- the fail-closed direction. A repository
 * without the workflow directory simply matches nothing and yields an
 * empty collection (a valid outcome the gate reports as
 * "0 workflow(s) audited").
 */
final readonly class WorkflowFileCollector
{
    private const string WORKFLOW_DIRECTORY = '/.github/workflows';

    public function __construct(private string $root)
    {
    }

    /**
     * @return array<string, string> map of file name => file contents, sorted
     *                               by file name ({@see SortedWorkflowMap} owns
     *                               the order -- the walk's own iteration order
     *                               belongs to the filesystem)
     */
    public function collect(): array
    {
        $workflows = [];

        $directory = $this->root . self::WORKFLOW_DIRECTORY;

        if (!is_dir($directory)) {
            return $workflows;
        }

        foreach (new DirectoryIterator($directory) as $file) {
            if ($file->isDir()) {
                continue;
            }

            $extension = $file->getExtension();

            if ($extension !== 'yml' && $extension !== 'yaml') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (!\is_string($contents)) {
                throw new ToolingException(\sprintf(
                    'Unable to read the workflow file "%s" for the concurrency audit.',
                    $file->getPathname(),
                ));
            }

            $workflows[$file->getFilename()] = $contents;
        }

        return SortedWorkflowMap::over($workflows);
    }
}
