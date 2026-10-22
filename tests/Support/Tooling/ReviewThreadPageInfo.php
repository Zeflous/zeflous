<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

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
        return $this->continuation($this->pageInfo());
    }

    /**
     * The continuation pair off the walked page answer, narrowed from
     * `mixed` and refused when the payload did not carry the shape.
     *
     * @param array<array-key, mixed> $pageInfo
     *
     * @return array{hasNextPage: bool, endCursor: null|string}
     */
    private function continuation(array $pageInfo): array
    {
        $hasNextPage = $pageInfo['hasNextPage'] ?? null;
        $endCursor = $pageInfo['endCursor'] ?? null;
        $shapeCarried = \is_bool($hasNextPage) && ($endCursor === null || \is_string($endCursor));

        if (!$shapeCarried) {
            throw new ToolingException(self::MISSING_PAGE_INFO);
        }

        return ['hasNextPage' => $hasNextPage, 'endCursor' => $endCursor];
    }

    /**
     * The pageInfo map the payload carries, walked one path key at a
     * time -- every level narrowed from `mixed`, a missing level the
     * refusal.
     *
     * @return array<array-key, mixed>
     */
    private function pageInfo(): array
    {
        $decoded = json_decode($this->json, true);

        if (!\is_array($decoded)) {
            // @infection-ignore-all The refusal's removal is
            // value-equivalent: a payload that does not decode to a map
            // walks on, and the walk's own null-coalescing read answers
            // null for every path key a non-map carries -- the is_array
            // refusal one statement later throws the identical message
            // for every payload this arm can ever see.
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

        return $value;
    }
}
