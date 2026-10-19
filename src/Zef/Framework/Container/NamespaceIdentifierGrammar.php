<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The indexed identifier's own grammar: the spelling checks an insert
 * owes before the walk runs -- split from
 * {@see NamespaceTreeBuilder} as its own unit (the
 * {@see NamespacePrefixGrammar} posture: the builder walks, the
 * grammar refuses).
 */
final readonly class NamespaceIdentifierGrammar
{
    /**
     * The identifier's segments and collapsed spelling, refusing the
     * spellings the tree cannot index.
     *
     * @return array{0: list<string>, 1: string} the segments and the collapsed spelling
     */
    public function segmentsOf(string $id): array
    {
        if ($id === '') {
            throw NamespaceBuiltRefusals::emptyIdentifier();
        }

        $segments = NamespaceSpellings::segments($id);

        if (\in_array('', $segments, true)) {
            throw NamespaceBuiltRefusals::emptySegment($id);
        }

        return [$segments, NamespaceSpellings::collapsed($id)];
    }
}
