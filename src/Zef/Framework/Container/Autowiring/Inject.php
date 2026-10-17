<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Attribute;

/**
 * The explicit service-reference attribute (the autowiring lane's
 * "named arguments / autowiring aliases" roadmap bullet).
 *
 * A constructor parameter carrying `#[Inject('logger.file')]` resolves
 * through the container under that exact identifier, regardless of the
 * parameter's own type: the declaration names the binding, the type hint
 * is not consulted. The identifier must exist at plan time -- another
 * declarative definition, a service the container already carries, or an
 * instantiable class the pass may birth under its own name -- anything
 * else is the honest refusal, never a guess.
 *
 * `#[Inject]` owns the parameter: combining it with {@see Target} is
 * rejected (two different binding intents on one parameter is an
 * ambiguity to surface, not a precedence to guess), and placing it on a
 * variadic parameter is rejected (a variadic is a collection, and
 * collections are the tags lane's concern, not a single reference).
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Inject
{
    public function __construct(
        public string $id,
    ) {
        if (mb_trim($id) === '') {
            throw new AutowiringException('#[Inject] requires a non-empty service identifier.');
        }
    }
}
