<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use SimpleXMLElement;

/**
 * Line-coverage figures parsed from a Clover XML report.
 *
 * The report is produced by `phpunit --coverage-clover`; this value object is
 * the only place that knows the Clover shape, so the gate logic stays pure.
 */
final readonly class CoverageReport
{
    private function __construct(
        public int $statements,
        public int $coveredStatements,
    ) {
    }

    public static function fromCloverFile(string $path): self
    {
        if (!is_file($path)) {
            throw new ToolingException(\sprintf('Coverage report not found: %s', $path));
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$xml instanceof SimpleXMLElement || !isset($xml->project->metrics)) {
            throw new ToolingException('Unable to read coverage metrics from the Clover report.');
        }

        $metrics = $xml->project->metrics;

        return new self(
            (int) $metrics['statements'],
            (int) $metrics['coveredstatements'],
        );
    }

    public function percentage(): float
    {
        if ($this->statements <= 0) {
            return 0.0;
        }

        return $this->coveredStatements / $this->statements * 100.0;
    }
}
