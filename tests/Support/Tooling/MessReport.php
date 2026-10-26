<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Parsed phpmd JSON payload (`--format=json` on stdout).
 *
 * Only the files the scanner visited carry violations; the payload's own
 * exit code (0 clean / 2 violations) is the tool's verdict, and this
 * report carries the findings so the gate can group them by rule.
 */
final readonly class MessReport
{
    /**
     * @param list<array{file: string, line: int, rule: string, description: string}> $violations
     */
    private function __construct(
        public array $violations,
    ) {
    }

    public static function fromJson(string $json, string $root): self
    {
        $data = json_decode($json, true);

        if (!\is_array($data)) {
            throw new ToolingException('The phpmd JSON payload is not valid JSON.');
        }

        $files = $data['files'] ?? null;

        if ($files === null) {
            return new self([]);
        }

        if (!\is_array($files)) {
            throw new ToolingException('The phpmd JSON payload carries a non-list files section.');
        }

        $violations = [];

        foreach ($files as $file) {
            if (!\is_array($file)) {
                throw new ToolingException('The phpmd JSON payload carries a non-map file entry.');
            }

            $fileViolations = $file['violations'] ?? null;

            if (!\is_array($fileViolations)) {
                throw new ToolingException('The phpmd JSON payload carries a non-list violations section.');
            }

            foreach ($fileViolations as $fileViolation) {
                $violations[] = self::violation($file['file'] ?? null, $fileViolation, $root);
            }
        }

        return new self($violations);
    }

    public function hasViolations(): bool
    {
        return $this->violations !== [];
    }

    /**
     * One finding narrowed onto the fields the message renders: a missing
     * field is a malformed payload the gate refuses to read silently
     * (fail-closed) rather than a violation it quietly drops.
     *
     * @param mixed $file      the file entry's own path member
     * @param mixed $violation the entry's one violation member
     *
     * @return array{file: string, line: int, rule: string, description: string}
     */
    private static function violation(mixed $file, mixed $violation, string $root): array
    {
        if (!\is_string($file) || !\is_array($violation)) {
            throw new ToolingException('The phpmd JSON payload carries a malformed violation.');
        }

        $line = $violation['beginLine'] ?? null;
        $rule = $violation['rule'] ?? null;
        $description = $violation['description'] ?? null;

        if (!\is_int($line) || !\is_string($rule) || !\is_string($description)) {
            throw new ToolingException('The phpmd JSON payload carries a malformed violation.');
        }

        return [
            'file' => self::relative($file, $root),
            'line' => $line,
            'rule' => $rule,
            'description' => $description,
        ];
    }

    /**
     * The scanner's absolute path trimmed to the repository-relative
     * spelling, so the gate's message reads the same in every
     * environment that runs it; a path the root does not prefix keeps
     * its full spelling so the message never names a file it cannot
     * point at.
     */
    private static function relative(string $path, string $root): string
    {
        $prefix = mb_rtrim($root, '/') . '/';

        return str_starts_with($path, $prefix)
            ? mb_substr($path, mb_strlen($prefix))
            : $path;
    }
}
