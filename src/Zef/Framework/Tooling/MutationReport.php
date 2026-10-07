<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Mutation-testing figures read from Infection's JSON log.
 *
 * The scores are taken verbatim from Infection's own reported metrics (which
 * correctly account for killed, escaped and timed-out mutants) instead of being
 * recomputed, so a gate can never diverge from the tool.
 */
final readonly class MutationReport
{
    private function __construct(
        public float $msi,
        public float $coveredMsi,
        public float $codeCoverage,
    ) {
    }

    public static function fromInfectionJsonFile(string $path): self
    {
        if (!is_file($path)) {
            throw new ToolingException(
                \sprintf('Mutation report not found: %s (run `composer mutation` first).', $path),
            );
        }

        $raw = file_get_contents($path);

        if (!\is_string($raw)) {
            throw new ToolingException('Unable to read the Infection JSON report.');
        }

        return self::fromInfectionJson($raw);
    }

    public static function fromInfectionJson(string $raw): self
    {
        $data = json_decode($raw, true);

        if (!\is_array($data)) {
            throw new ToolingException('Unable to parse the Infection JSON report.');
        }

        $stats = $data['stats'] ?? null;

        if (!\is_array($stats)) {
            throw new ToolingException('The Infection report does not contain mutation statistics.');
        }

        foreach (['msi', 'coveredCodeMsi', 'mutationCodeCoverage'] as $required) {
            if (!\array_key_exists($required, $stats)) {
                throw new ToolingException(
                    \sprintf('The Infection report is missing the "%s" metric.', $required),
                );
            }
        }

        return new self(
            self::floatMetric($stats, 'msi'),
            self::floatMetric($stats, 'coveredCodeMsi'),
            self::floatMetric($stats, 'mutationCodeCoverage'),
        );
    }

    /**
     * @param array<array-key, mixed> $stats
     */
    private static function floatMetric(array $stats, string $key): float
    {
        $value = $stats[$key];

        if (!\is_int($value) && !\is_float($value) && (!\is_string($value) || !is_numeric($value))) {
            throw new ToolingException(
                \sprintf('The Infection report metric "%s" is not numeric.', $key),
            );
        }

        return (float) $value;
    }
}
