<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use GlobIterator;

/**
 * Collects the GitHub Actions workflow files that the concurrency gate audits.
 *
 * This is the only side of the workflow-concurrency report that touches the
 * filesystem, so the gate itself stays pure and fully unit-testable.
 *
 * Only the top level of `.github/workflows` is collected, mirroring GitHub's
 * own workflow discovery: nested directories are ignored by GitHub Actions,
 * so collecting them would audit files that never run. The two glob patterns
 * pre-filter the YAML extensions, and a file that cannot be read is left out
 * instead of aborting the whole audit.
 */
final readonly class WorkflowFileCollector
{
    private const string WORKFLOW_DIRECTORY = '/.github/workflows';

    private const string YML_PATTERN = '/*.yml';

    private const string YAML_PATTERN = '/*.yaml';

    public function __construct(private string $root)
    {
    }

    /**
     * @return array<string, string> map of file name => file contents, sorted by file name
     */
    public function collect(): array
    {
        $directory = $this->root . self::WORKFLOW_DIRECTORY;
        $workflows = [];

        if (!is_dir($directory)) {
            return $workflows;
        }

        $files = [
            ...new GlobIterator($directory . self::YML_PATTERN),
            ...new GlobIterator($directory . self::YAML_PATTERN),
        ];

        foreach ($files as $file) {
            $path = (string) $file;

            $contents = file_get_contents($path);

            if (!\is_string($contents)) {
                continue;
            }

            $workflows[basename($path)] = $contents;
        }

        ksort($workflows);

        return $workflows;
    }
}
