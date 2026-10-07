<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Parsed `composer audit --format=json` payload.
 */
final readonly class DependencyAudit
{
    /**
     * @param list<string> $advisories
     * @param list<string> $abandoned
     */
    private function __construct(
        public array $advisories,
        public array $abandoned,
    ) {
    }

    public static function fromComposerAuditJson(string $raw): self
    {
        $data = json_decode($raw, true);

        if (!\is_array($data)) {
            throw new ToolingException('Composer audit payload is not valid JSON.');
        }

        return new self(
            self::packageNames($data['advisories'] ?? null),
            self::packageNames($data['abandoned'] ?? null),
        );
    }

    /**
     * @param list<string> $allowlist
     *
     * @return list<string>
     */
    public function blockedAdvisories(array $allowlist): array
    {
        return array_values(array_diff($this->advisories, $allowlist));
    }

    /**
     * @param list<string> $allowlist
     *
     * @return list<string>
     */
    public function blockedAbandoned(array $allowlist): array
    {
        return array_values(array_diff($this->abandoned, $allowlist));
    }

    /**
     * @return list<string>
     */
    private static function packageNames(mixed $section): array
    {
        if (!\is_array($section)) {
            return [];
        }

        $names = [];

        foreach (array_keys($section) as $name) {
            $names[] = (string) $name;
        }

        return $names;
    }
}
