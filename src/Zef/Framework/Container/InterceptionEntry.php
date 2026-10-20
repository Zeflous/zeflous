<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One interception entry's parsed form: the anonymous builder declaration
 * both the `interceptors` knob and the `listeners` knob carry -- one
 * shape ('class' or 'factory'), a positional argument list, and (the
 * interceptor knob alone) the priority that orders the onion.
 *
 * The entry grammar is the definition grammar narrowed to what an
 * interceptor or listener is: a builder shape plus arguments. It
 * carries no lifetime or tags of its own -- the materialised object is
 * boot machinery, never a container service, so there is nothing for a
 * sharing policy or a grouping label to ride on. The `['service' =>
 * id]` reference form is equally absent from the entry *itself*: an
 * interceptor resolved through the container would run through the
 * very seam it arms, so the lane materialises its entries directly.
 * The arguments follow the ordinary reference grammar and resolve
 * through the container at materialisation time.
 *
 * The value object stays a value object: the parse pipeline lives in
 * {@see InterceptionEntryParser} (the shape parsers it dispatches to
 * may not reference this class back, or the grammar's own vocabulary
 * closes a dependency cycle -- the decoration lane's own lesson).
 */
final readonly class InterceptionEntry
{
    /**
     * @param ?string     $class     the builder's class when class-shaped
     *                               (class-string), null otherwise
     * @param ?string     $factory   the builder's 'Target::method' spelling
     *                               when factory-shaped, null otherwise
     * @param list<mixed> $arguments the validated argument list
     * @param int         $priority  the onion order (highest first); the
     *                               listener knobs always parse 0 -- their
     *                               firing order is the declaration order
     */
    public function __construct(
        /** @var null|class-string */
        public ?string $class,
        public ?string $factory,
        public array $arguments,
        public int $priority = 0,
    ) {
    }
}
