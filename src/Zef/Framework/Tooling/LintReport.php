<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Collects the PHP files that the syntax-lint gate must check.
 */
final readonly class LintReport
{
    /**
     * @param list<string> $files
     */
    private function __construct(public array $files)
    {
    }

    /**
     * @param list<string> $directories
     */
    public static function collectPhpFiles(string $root, array $directories): self
    {
        $files = [];

        foreach ($directories as $directory) {
            $path = $root . \DIRECTORY_SEPARATOR . $directory;

            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (!($file instanceof SplFileInfo) || $file->getExtension() !== 'php') {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return new self($files);
    }

    public function count(): int
    {
        return \count($this->files);
    }
}
