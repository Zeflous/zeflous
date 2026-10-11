<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * One node of the compiled radix tree.
 *
 * The tree is built once by {@see RouteTreeBuilder} and then frozen: this
 * class carries no public mutator, and the only writer (the builder) is
 * gone by the time {@see RadixTree::match()} walks the nodes, so a
 * compiled tree is immutable by construction -- the roadmap's "Immutable
 * Tree Structure".
 *
 * A node owns a static text prefix (`/users/`, possibly spanning several
 * segments -- the radix compression merges runs of shared text), at most
 * one anonymous parameter edge (it consumes one whole non-empty request
 * segment, whatever its value), and at most one route payload: the route
 * whose pattern ends exactly at this node.
 *
 * All string arithmetic is character-based (`mb_*`), so a tree built from
 * multi-byte static text matches exactly like ASCII text.
 *
 * @internal
 */
final class RadixNode
{
    /** @var array<string, self> static children keyed by first character */
    private array $staticChildren = [];

    private ?self $parameterChild = null;

    private ?CompiledRoute $compiledRoute = null;

    public function __construct(private string $prefix)
    {
    }

    /**
     * The static text this node consumes.
     */
    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * The static child whose prefix starts with the given character.
     */
    public function staticChild(string $firstCharacter): ?self
    {
        return $this->staticChildren[$firstCharacter] ?? null;
    }

    /**
     * The anonymous parameter edge, or null when no parameter continues
     * here.
     */
    public function parameterChild(): ?self
    {
        return $this->parameterChild;
    }

    /**
     * The route whose pattern ends at this node, or null.
     */
    public function route(): ?CompiledRoute
    {
        return $this->compiledRoute;
    }

    /**
     * Registers a route whose pattern ends exactly at this node
     * (builder-only).
     */
    public function setRoute(CompiledRoute $compiledRoute): void
    {
        $this->compiledRoute = $compiledRoute;
    }

    /**
     * Attaches (or re-attaches) the anonymous parameter edge
     * (builder-only).
     */
    public function setParameterChild(self $node): void
    {
        $this->parameterChild = $node;
    }

    /**
     * Creates a child owning a whole static run and attaches it under
     * the run's first character (builder-only).
     */
    public function appendStaticRun(string $text): self
    {
        $child = new self($text);
        $this->appendStaticChild($child);

        return $child;
    }

    /**
     * Splits this node's prefix: the node keeps the first `$length`
     * characters, and a new node is attached beneath it owning the rest
     * together with everything this node used to hold (children,
     * parameter edge, route) (builder-only).
     */
    public function splitAt(int $length): void
    {
        $remainder = new self(mb_substr($this->prefix, $length));
        $remainder->staticChildren = $this->staticChildren;
        $remainder->parameterChild = $this->parameterChild;
        $remainder->compiledRoute = $this->compiledRoute;

        $this->prefix = mb_substr($this->prefix, 0, $length);
        $this->staticChildren = [];
        $this->parameterChild = null;
        $this->compiledRoute = null;
        $this->appendStaticChild($remainder);
    }

    /**
     * Adds a static child under its first character (builder-only).
     */
    private function appendStaticChild(self $node): void
    {
        $this->staticChildren[mb_substr($node->prefix(), 0, 1)] = $node;
    }
}
