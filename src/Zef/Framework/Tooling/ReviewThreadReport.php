<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Parses a GitHub GraphQL `reviewThreads` payload into {@see ReviewThread}
 * objects and exposes the ids of the threads that are finished conversations.
 *
 * The parser is fail-closed: a payload that is not valid JSON, or that does not
 * carry the expected `reviewThreads.nodes` shape, raises a
 * {@see ToolingException} rather than being treated as "no threads" -- a silent
 * empty result would let the lane believe a blocked pull request has nothing to
 * resolve.
 */
final readonly class ReviewThreadReport
{
    /**
     * The path from the GraphQL root to the thread node list.
     */
    private const array NODE_PATH = ['data', 'repository', 'pullRequest', 'reviewThreads', 'nodes'];

    /**
     * @param list<ReviewThread> $threads
     */
    private function __construct(public array $threads)
    {
    }

    public static function fromGraphQlPayload(string $json): self
    {
        $decoded = json_decode($json, true);

        if (!\is_array($decoded)) {
            throw new ToolingException('Review-thread payload is not valid JSON.');
        }

        $nodes = self::dig($decoded, self::NODE_PATH);

        if (!\is_array($nodes)) {
            throw new ToolingException('Review-thread payload is missing reviewThreads.nodes.');
        }

        $threads = [];

        foreach ($nodes as $node) {
            $thread = ReviewThreadFactory::fromNode($node);

            if (!$thread instanceof ReviewThread) {
                continue;
            }

            $threads[] = $thread;
        }

        return new self($threads);
    }

    /**
     * The ids of every thread that is a finished conversation, in payload order.
     *
     * @return list<string>
     */
    public function resolvableIds(): array
    {
        $ids = [];

        foreach ($this->threads as $thread) {
            if (!$thread->isFinishedConversation()) {
                continue;
            }

            $ids[] = $thread->id;
        }

        return $ids;
    }

    public function count(): int
    {
        return \count($this->threads);
    }

    /**
     * Walks a nested payload one key at a time, returning null as soon as a
     * level is not an array. json_decode() returns `mixed`, so each level is
     * narrowed before it is indexed.
     *
     * @param array<array-key, mixed> $decoded
     * @param list<string>            $keys
     */
    private static function dig(array $decoded, array $keys): mixed
    {
        $value = $decoded;

        foreach ($keys as $key) {
            if (!\is_array($value)) {
                return null;
            }

            $value = $value[$key] ?? null;
        }

        return $value;
    }
}
