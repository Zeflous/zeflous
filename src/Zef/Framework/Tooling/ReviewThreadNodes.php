<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The `reviewThreads.nodes` list inside a decoded GitHub GraphQL payload.
 *
 * The list sits five levels deep (`data.repository.pullRequest.reviewThreads
 * .nodes`), and every level of the `mixed` json_decode() output must be
 * narrowed before it can be indexed safely. This class owns that walk: it
 * either returns the node list or throws a {@see ToolingException} naming
 * the missing shape, so a malformed payload can never be mistaken for an
 * empty one.
 */
final readonly class ReviewThreadNodes
{
    private const string MISSING_NODES = 'Review-thread payload is missing reviewThreads.nodes.';

    private const array NODE_PATH = ['data', 'repository', 'pullRequest', 'reviewThreads', 'nodes'];

    /**
     * @param array<array-key, mixed> $decoded
     */
    public function __construct(private array $decoded)
    {
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toList(): array
    {
        $value = $this->decoded;

        foreach (self::NODE_PATH as $key) {
            $next = $value[$key] ?? null;

            if (!\is_array($next)) {
                throw new ToolingException(self::MISSING_NODES);
            }

            $value = $next;
        }

        return $value;
    }
}
