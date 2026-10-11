<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use CallbackFilterIterator;
use FilesystemIterator;
use Iterator;
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

            /**
             * The filter's own guarantee: every yielded value passed the
             * `instanceof SplFileInfo` check, so the iteration below is
             * typed without re-narrowing it.
             *
             * @var Iterator<SplFileInfo> $phpFiles
             */
            $phpFiles = new CallbackFilterIterator(
                $iterator,
                static fn (mixed $file): bool => $file instanceof SplFileInfo && $file->getExtension() === 'php',
            );

            foreach ($phpFiles as $phpFile) {
                $files[] = $phpFile->getPathname();
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
