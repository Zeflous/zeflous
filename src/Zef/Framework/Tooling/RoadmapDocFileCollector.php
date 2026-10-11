<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Collects the roadmap / delivery documents that the documentation-integrity
 * gate audits.
 *
 * This is the only side of the report that touches the filesystem, so the gate
 * itself stays pure and fully unit-testable.
 *
 * A document that cannot be read is left out instead of aborting the whole
 * audit -- the repository may legitimately not carry one of them yet. A skip
 * is honest about absence; it is NOT a silent pass, because the corpus is a
 * fixed, in-tree list and a document that vanishes is a review-time change,
 * not a runtime surprise.
 */
final readonly class RoadmapDocFileCollector
{
    /**
     * The documents the integrity gate owns. They are the repository's public
     * record of what shipped and where it stands, and the ones a merged pull
     * request is required to update (`CONTRIBUTING.md` rule 13).
     */
    private const array DOCUMENTS = [
        'ROADMAP-HISTORY.md',
        'ROADMAP-STATUS.md',
        'CONTRIBUTING.md',
    ];

    public function __construct(private string $root)
    {
    }

    /**
     * @return array<string, string> map of file name => file contents
     */
    public function collect(): array
    {
        $documents = [];

        foreach (self::DOCUMENTS as $name) {
            $path = $this->root . '/' . $name;

            if (!is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            $documents[$name] = $contents;
        }

        return $documents;
    }
}
