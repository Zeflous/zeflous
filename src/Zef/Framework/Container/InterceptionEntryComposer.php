<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interception entry's shape composition: the one shape the
 * validated map carries, composed into the parsed {@see
 * InterceptionEntry} from the shape's own proofs -- the class arm
 * through {@see InterceptionClassShape}'s existence proof, the factory
 * arm through the shared {@see FactorySpellings} grammar, both over
 * the same validated argument list ({@see InterceptionEntryArguments})
 * and the caller's priority.
 *
 * Split from {@see InterceptionEntryParser} so the grammar's entry
 * points (the two knobs' own key vocabularies and guards) and the
 * composition they dispatch to stay archeology-sized units of their
 * own -- the parser's publics answer "is this an entry at all", this
 * unit answers "what the proven entry is".
 */
final readonly class InterceptionEntryComposer
{
    /**
     * Composes the validated entry map into its parsed form.
     *
     * @param array<array-key, mixed> $entry
     */
    public static function composed(string $label, array $entry, int $priority): InterceptionEntry
    {
        $arguments = InterceptionEntryArguments::validated($label, $entry);

        return match (InterceptionEntryShapes::shapeOf($label, $entry)) {
            'class' => new InterceptionEntry(
                InterceptionClassShape::existingClass($label, $entry['class']),
                null,
                $arguments,
                $priority,
            ),
            default => new InterceptionEntry(
                null,
                FactorySpellings::validated($label, $entry['factory']),
                $arguments,
                $priority,
            ),
        };
    }
}
