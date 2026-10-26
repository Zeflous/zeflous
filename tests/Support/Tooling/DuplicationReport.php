<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use SimpleXMLElement;

/**
 * Parsed phpcpd PMD-CPD payload (`--log-pmd php://stdout`).
 *
 * The detector's own exit code (0 clean / 1 clones found) is the tool's
 * verdict; this report carries the clone pairs' spans so the gate can
 * name them. Paths are relativised against the repository root so the
 * message reads the same in every environment that runs the gate.
 */
final readonly class DuplicationReport
{
    /**
     * @param list<array{files: list<array{path: string, line: int, endLine: int}>, lines: int}> $clones
     */
    private function __construct(
        public array $clones,
    ) {
    }

    public static function fromPmdXml(string $xml, string $root): self
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$document instanceof SimpleXMLElement) {
            throw new ToolingException('The phpcpd PMD payload is not valid XML.');
        }

        $clones = [];

        foreach ($document->duplication ?? [] as $duplication) {
            $files = [];

            foreach ($duplication->file ?? [] as $file) {
                $files[] = [
                    'path' => self::relative((string) $file['path'], $root),
                    'line' => (int) $file['line'],
                    'endLine' => (int) $file['endline'],
                ];
            }

            if ($files === []) {
                throw new ToolingException('The phpcpd PMD payload carries a duplication without file spans.');
            }

            $clones[] = ['files' => $files, 'lines' => (int) $duplication['lines']];
        }

        return new self($clones);
    }

    public function hasClones(): bool
    {
        return $this->clones !== [];
    }

    /**
     * The absolute PMD path trimmed to the repository-relative spelling;
     * a path the root does not prefix (a path from another mount, a
     * report produced elsewhere) keeps its full spelling so the message
     * never names a file it cannot point at.
     */
    private static function relative(string $path, string $root): string
    {
        $prefix = mb_rtrim($root, '/') . '/';

        return str_starts_with($path, $prefix)
            ? mb_substr($path, mb_strlen($prefix))
            : $path;
    }
}
