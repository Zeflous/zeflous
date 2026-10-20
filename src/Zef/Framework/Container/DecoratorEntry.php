<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One decorator entry's parsed form: the anonymous declaration the
 * decoration lane wraps a target with -- one shape ('class' or
 * 'factory'), a positional argument list that spells the reserved
 * inner reference at least once, and nothing else.
 *
 * The entry grammar is the definition grammar narrowed to what a
 * wrapper is: a builder shape plus arguments (a wrapper carries no
 * lifetime or tags of its own -- every layer takes the decorated
 * declaration's lifetime, and the tags travel with the original
 * identifier, the ZEF-DEEP-08 discipline the reference container
 * learned the hard way). The value object stays a value object: the
 * parse pipeline lives in {@see DecoratorEntryParser} (the shape
 * parsers it dispatches to may not reference this class back, or the
 * grammar's own vocabulary closes a dependency cycle), the arguments
 * are validated by {@see DecoratorEntryArguments} under the knob's own
 * label, and the class shape's existence proof is
 * {@see DecoratorClassShape}'s.
 */
final readonly class DecoratorEntry
{
    /**
     * @param ?string     $class     the wrapper's class when class-shaped
     *                               (class-string), null otherwise
     * @param ?string     $factory   the wrapper's 'Target::method' spelling when
     *                               factory-shaped, null otherwise
     * @param list<mixed> $arguments the validated argument list; at least
     *                               one entry references '@inner'
     */
    public function __construct(
        /** @var null|class-string */
        public ?string $class,
        public ?string $factory,
        public array $arguments,
    ) {
    }
}
