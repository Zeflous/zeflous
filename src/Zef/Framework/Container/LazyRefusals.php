<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The lazy lane's refusal messages -- every way a lazy declaration, a
 * lazy marking or a lazy resolution can fail, each with the
 * declaration's own identifier and the way out in the message.
 *
 * Kept its own archeology-sized unit the way the contextual lane's
 * refusals are ({@see ContextualRefusals}): the lane keeps the
 * verdicts, this class keeps the words.
 */
final readonly class LazyRefusals
{
    /** The type-given tail the shape refusals share (the S1192 line: one spelling). */
    private const string GIVEN = ' given.';

    public static function keyNotABoolean(string $id, mixed $key): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' lazy must be a boolean, " . get_debug_type($key) . self::GIVEN,
        );
    }

    public static function requestLifetime(string $id): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' cannot be lazy with a request lifetime: a deferred materialisation "
            . 'cannot promise the scope it will run in (declare singleton or transient, '
            . 'or drop the lazy key).',
        );
    }

    public static function classNotInstantiable(string $id, string $class): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' cannot be lazy behind the class '{$class}': a native lazy proxy "
            . 'is an instance of the class itself, and an abstract class, an interface or an '
            . 'enum has none (declare a concrete class).',
        );
    }

    public static function returnUndeclared(string $id, string $factory): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' cannot be lazy behind the factory '{$factory}': the factory declares "
            . 'no return type, so the declaration cannot name the class its proxy defers behind '
            . '(declare the product class as the return type).',
        );
    }

    public static function targetNotAClass(string $id, string $factory, string $target): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' cannot be lazy behind the factory '{$factory}': the factory target "
            . "'{$target}' is not a class the runtime carries (a lazy factory names its product "
            . 'at the parse, and the service-instance form\'s product class is not nameable there — '
            . 'spell the factory as Class::method).',
        );
    }

    public static function targetMethodUnknown(
        string $id,
        string $factory,
        string $target,
        string $method,
    ): DefinitionException {
        return new DefinitionException(
            "Service '{$id}' cannot be lazy behind the factory '{$factory}': the factory target "
            . "'{$target}' declares no method '{$method}' (the lazy grammar reflects the factory "
            . 'at the parse — the eager spelling takes this refusal at the first resolution).',
        );
    }

    public static function returnNotOneClass(string $id, string $factory, string $what): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' cannot be lazy behind the factory '{$factory}': the factory's return "
            . "type is {$what}, and a native lazy proxy needs exactly one declared class "
            . '(declare the product class as the return type).',
        );
    }

    public static function returnSelfSpelled(string $id, string $factory, string $spelled): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' cannot be lazy behind the factory '{$factory}': the factory's return "
            . "type is spelled '{$spelled}', which reflection does not resolve to a class name "
            . '(declare the product class itself as the return type).',
        );
    }

    public static function returnClassUnknown(string $id, string $factory, string $class): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' cannot be lazy behind the factory '{$factory}': the declared return "
            . "class '{$class}' does not exist.",
        );
    }

    public static function unknownService(string $id, string $class): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' cannot be marked lazy behind the class '{$class}': the identifier "
            . 'carries no registration to defer (register the service before marking it lazy).',
        );
    }

    public static function lifetimeCannotDefer(string $id): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' cannot be marked lazy: a request-lifetime registration cannot defer "
            . 'its materialisation into a scope it cannot name.',
        );
    }

    public static function classDisagreement(string $id, string $marked, string $class): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' is already marked lazy behind the class '{$marked}' and cannot be "
            . "re-marked behind the class '{$class}' (re-register the service, or mark the "
            . 'identifier with one class only).',
        );
    }
}
