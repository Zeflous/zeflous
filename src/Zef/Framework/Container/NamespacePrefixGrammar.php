<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One scope annotation's own grammar: the prefix's spelling validation,
 * answering the collapsed prefix the annotation keys under -- split
 * from {@see NamespaceScopeParser} as its own unit so the parser keeps
 * its loop and the grammar keeps its refusals (the
 * {@see DecoratorEntryParser} posture: the parser orchestrates, the
 * grammar validates).
 *
 * The key's own string typing is the parser's walk check (the
 * parameters lane's own map-walk posture), and the kind's vocabulary
 * check lives in the parser's own loop -- one grammar unit carrying
 * either half would outgrow the word-owning size the lane's
 * vocabulary units keep.
 */
final readonly class NamespacePrefixGrammar
{
    /**
     * Validates one annotation's prefix spelling, answering the
     * collapsed prefix it keys under.
     *
     * @param string $key    the knob's own key, for the refusals
     * @param string $prefix the annotation key, a string by the walk's own check
     */
    public function collapsed(string $key, string $prefix): string
    {
        if ($prefix === '') {
            throw NamespaceRefusals::prefixNotANonEmptyString($key, $prefix);
        }

        $collapsed = NamespaceSpellings::prefix($prefix);

        if ($collapsed === '') {
            throw NamespaceRefusals::rootPrefix($key, $prefix);
        }

        $trimmed = mb_rtrim($collapsed, NamespaceSpellings::SEPARATOR);

        if (\in_array('', NamespaceSpellings::segments($trimmed), true)) {
            throw NamespaceRefusals::emptySegment($key, $prefix);
        }

        return $collapsed;
    }
}
