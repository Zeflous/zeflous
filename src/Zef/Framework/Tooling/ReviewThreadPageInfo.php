<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The `reviewThreads.pageInfo` pagination answer inside a GitHub GraphQL
 * payload.
 *
 * The connection caps at one hundred nodes a page, so a pull request with
 * more threads answers its later pages only behind a cursor: the page's
 * own `hasNextPage`/`endCursor` pair is the only honest truncation signal
 * -- `totalCount` counts what GitHub holds, never what one page carried.
 * The walk narrows every `mixed` json_decode() level and refuses a payload
 * without the shape: a silently missing page answer would let the resolver
 * believe a blocked pull request has nothing left beyond its first page.
 */
final readonly class ReviewThreadPageInfo
{
    private const string MISSING_PAGE_INFO = 'Review-thread payload is missing reviewThreads.pageInfo.';

    private const array PAGE_PATH = ['data', 'repository', 'pullRequest', 'reviewThreads', 'pageInfo'];

    public function __construct(private string $json)
    {
    }

    /**
     * The page's own continuation answer: whether another page follows,
     * and the cursor it starts after.
     *
     * @return array{hasNextPage: bool, endCursor: null|string}
     */
    public function page(): array
    {
        $decoded = json_decode($this->json, true);

        if (!\is_array($decoded)) {
            throw new ToolingException(self::MISSING_PAGE_INFO);
        }

        $value = $decoded;

        foreach (self::PAGE_PATH as $key) {
            $next = $value[$key] ?? null;

            if (!\is_array($next)) {
                throw new ToolingException(self::MISSING_PAGE_INFO);
            }

            $value = $next;
        }

        $hasNextPage = $value['hasNextPage'] ?? null;
        $endCursor = $value['endCursor'] ?? null;

        if (!\is_bool($hasNextPage) || ($endCursor !== null && !\is_string($endCursor))) {
            throw new ToolingException(self::MISSING_PAGE_INFO);
        }

        return ['hasNextPage' => $hasNextPage, 'endCursor' => $endCursor];
    }
}
