<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Attribute;
use Zef\Framework\Container\ParameterKeys;

/**
 * The configuration-value attribute (the registration-completion
 * family's seam into the autowiring lane, ported from the reference
 * container's `#[Value]`).
 *
 * A scalar constructor parameter carrying `#[Value('db.host')]`
 * resolves its value from the `parameters` configuration at boot:
 *
 *     public function __construct(#[Value('db.host')] string $host) {}
 *
 * The value is materialised as the synthetic `@value:db.host` value
 * service -- one shared instance, carried in the boot graph and
 * validated like any other dependency -- and the plan references it
 * through the ordinary `['service' => id]` grammar, so the lane adds
 * zero new runtime machinery ({@see AutowireValueServices}).
 *
 * `#[Value]` owns the parameter: combining it with {@see Inject} or
 * {@see Target} is rejected (one parameter carries one binding
 * intent), placing it on a variadic parameter is rejected (a
 * collection is not one value), and a class-typed or union parameter
 * is rejected (a value is a scalar or an array, never an instance or
 * a type union -- the constructor's own default is the fallback when
 * the attribute is absent). The key shares the parameters family's
 * address grammar ({@see ParameterKeys}), so an attribute key and a
 * map key can never disagree about what an address is.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Value
{
    /**
     * The constructor's caller is the reflection API itself:
     * {@see AutowireAttributes} reads the attribute through
     * `ReflectionAttribute::newInstance()`, so the key's own grammar
     * validation runs at the read -- a caller static analysis cannot
     * see, and the psalm baseline's unused verdict records honestly.
     */
    public function __construct(
        public string $key,
    ) {
        ParameterKeys::assert('#[Value] key', $key);
    }
}
