<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Collects the GitHub Actions workflow files that the concurrency gate audits.
 *
 * The gate logic is pure; this report is the only place that touches the
 * filesystem, so the gate can be exercised deterministically in tests.
 */
final readonly class WorkflowConcurrencyReport
{
    /**
     * @param array<string, string> $workflows map of file name => file contents
     */
    private function __construct(public array $workflows)
    {
    }

    public static function collect(string $root): self
    {
        $directory = $root . '/.github/workflows';

        if (!is_dir($directory)) {
            return new self([]);
        }

        $workflows = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!($file instanceof SplFileInfo)) {
                continue;
            }

            $extension = $file->getExtension();

            if ($extension !== 'yml' && $extension !== 'yaml') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (!\is_string($contents)) {
                continue;
            }

            $workflows[$file->getFilename()] = $contents;
        }

        ksort($workflows);

        return new self($workflows);
    }

    public function count(): int
    {
        return \count($this->workflows);
    }
}
