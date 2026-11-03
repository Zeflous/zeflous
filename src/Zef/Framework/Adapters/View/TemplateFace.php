<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\View;

use InvalidArgumentException;
use Stringable;
use Zef\Framework\View\ViewEngine;
use Zef\Framework\View\ViewException;
use Zef\Framework\View\ViewName;

/**
 * The template's own handle on the engine: the ONE object a native
 * template sees beyond its data, handed into every scope as the
 * reserved `$_view` variable the data key corridor's grammar keeps
 * collision-free. Two verbs, no more -- the escape fold and the
 * partial verb -- because a template's whole vocabulary beyond its
 * data is exactly the questions the port already spells.
 *
 * The ESCAPE fold is the tree's one spelling of HTML-safe text
 * (`htmlspecialchars` over `ENT_QUOTES | ENT_SUBSTITUTE` and UTF-8),
 * the same line the browser error page's own renderer spells
 * privately: the native answer's escaping doctrine is the
 * template's own discipline -- every interpolated value crosses
 * this fold -- and the fold is the one way the answer hands that
 * discipline to its authors (a future Twig answer satisfies the
 * port's escaping posture natively through its own
 * auto-escape-on-output, and needs no face verb for it).
 *
 * The PARTIAL verb takes the extension-free name and the data bag
 * the port itself takes, and re-enters the same engine: the
 * composition a layout spells through its partials is the same
 * question asked recursively, bounded by the engine's own nesting
 * ledger, and a partial's failure travels the same typed family the
 * outer render's does.
 */
final readonly class TemplateFace
{
    public function __construct(
        private ViewEngine $engine,
    ) {
    }

    /**
     * Escapes one value into HTML-safe text -- the fold every
     * interpolated value crosses, the tree's one escaping spelling.
     */
    public function escape(Stringable|string $value): string
    {
        return htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Renders one partial through the same engine -- the port's own
     * question asked from inside a template, bounded by the engine's
     * own nesting ledger.
     *
     * @param array<string, mixed> $data the partial's own bag
     *
     * @throws InvalidArgumentException when the name is outside the grammar the identity fold refuses
     * @throws ViewException when the partial does not answer, escapes,
     *                       refuses the bag, or fails mid-render
     */
    public function render(string $name, array $data): string
    {
        return $this->engine->render(new ViewName($name), $data);
    }
}
