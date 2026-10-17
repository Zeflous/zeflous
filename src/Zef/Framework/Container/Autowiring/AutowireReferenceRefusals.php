<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The reference arms' refusal messages (see {@see AutowireReferences}):
 * the unbound identifier (with every way out), the `#[Target]` that
 * cannot instantiate, and the circular birth chain.
 *
 * Split from the arms so the vocabulary of the refusals -- the operand
 * weight of the messages -- stays its own archeology-sized unit: the
 * arms keep the resolution tiers, this class keeps the words.
 */
final readonly class AutowireReferenceRefusals
{
    /**
     * One identifier no binding answers -- with every way out in the
     * message.
     */
    public static function unbound(string $ownerId, string $name, string $id): AutowiringException
    {
        return new AutowiringException(
            "Cannot autowire '{$ownerId}' parameter '\${$name}': no binding for '{$id}'. "
            . 'Register the service, alias the type name to an implementation, bind the parameter '
            . 'with #[Inject] or #[Target], or give the parameter a default value.',
        );
    }

    /**
     * A `#[Target]` binding that cannot produce an instance -- a
     * declaration defect, not a runtime surprise.
     */
    public static function targetNotInstantiable(string $ownerId, string $name, string $class): AutowiringException
    {
        return new AutowiringException(
            "Cannot autowire '{$ownerId}' parameter "
            . "'\${$name}': #[Target({$class}::class)] is not an instantiable class.",
        );
    }

    /**
     * A birth chain that closed onto itself, with the walked path.
     */
    public static function circularChain(string $chainText): AutowiringException
    {
        return new AutowiringException('The birth chain is circular: ' . $chainText . '.');
    }
}
