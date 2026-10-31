<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The where-family's state: the ordered fragment list and the bound
 * values the conditions carry, joined by AND/OR connectors with the
 * first fragment bare -- the record the compilers read at build time.
 *
 * @internal
 */
final class QueryWhereState
{
    /** @var list<string> */
    private array $fragments = [];

    /** @var list<mixed> */
    private array $params = [];

    /**
     * Records one rendered fragment and its bound values, ANDed unless
     * the caller spells the OR connector.
     *
     * @param list<mixed> $params
     */
    public function add(string $fragment, array $params, string $connector = 'AND'): void
    {
        if ($this->fragments !== []) {
            $this->fragments[] = $connector . ' ' . $fragment;
        } else {
            $this->fragments[] = $fragment;
        }

        $this->params = array_merge($this->params, $params);
    }

    /**
     * The rendered ` WHERE ...` suffix, empty when no condition lives
     * here yet.
     */
    public function suffix(): string
    {
        if ($this->fragments === []) {
            return '';
        }

        return ' WHERE ' . implode(' ', $this->fragments);
    }

    /**
     * @return list<mixed>
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * True when no condition lives here yet.
     */
    public function isEmpty(): bool
    {
        return $this->fragments === [];
    }
}
