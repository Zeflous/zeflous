<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One namespace scope answer: the annotation a lookup or an enforcement
 * walk found -- the collapsed, trailing-separator prefix that owns the
 * subtree, and the kind the prefix was annotated with (one of the
 * {@see NamespaceRadixTree::SCOPES} kinds).
 *
 * The null counterpart (no annotation at all -- public by convention,
 * ungoverned) is the scopeOf answer's own spelling, so the locator's
 * consumer never has to distinguish "no scope" from "the public scope":
 * both are honest answers, and the value object is only ever the one
 * that was actually declared.
 */
final readonly class NamespaceScope
{
    /**
     * @param string $prefix the collapsed, trailing-separator prefix
     * @param string $kind   the scope kind the prefix carries
     */
    public function __construct(
        public string $prefix,
        public string $kind,
    ) {
    }
}
