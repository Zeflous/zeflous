<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Zef\Framework\Container\AliasDefinition;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\MethodCall;
use Zef\Framework\Container\MethodCallInvocations;
use Zef\Framework\Container\ValueDefinition;

/**
 * The closure rendering of the artifact boundary: the three shapes whose
 * factories become pure code -- the instance, the alias and the value --
 * rendered as self-contained `static fn` expressions the artifact's array
 * literal embeds verbatim, so a single `require` turns them into live
 * closures with no `eval`, no reflection and no machinery at load.
 *
 * The instance shape is the lane's headline: the full pipeline's
 * `ReflectionClass::newInstanceArgs()` over a runtime argument walk
 * becomes the literal `new \Class($c->get('dep'), 42, ['k' => 'v'])` --
 * the arguments inlined at their call positions, the dependency calls
 * spelled exactly where the runtime walk would have resolved them
 * (ROADMAP area 8's "Inlining & optimization", the zero-reflection
 * promise {@see \Zef\Framework\Container\DefinitionCompiler}'s own
 * docblock makes for this lane). A declaration carrying `calls` keeps
 * the method-call injection's one authority: the rendered closure
 * delegates to {@see MethodCallInvocations::apply()}
 * over literally reconstructed {@see MethodCall} objects, so the calls'
 * semantics and their failure messages stay single-sourced.
 *
 * The alias and value shapes render their one-line equivalents of the
 * compiler's own closures. The factory shape does not render here at
 * all: it crosses the boundary as data and is rebuilt once at load
 * ({@see AotDefinitions}), because its resolution lane owns conditional
 * behaviour (an instance call on a registered target, a static call
 * otherwise) whose failure messages must stay the
 * {@see \Zef\Framework\Container\FactoryClosures} family's alone.
 */
final readonly class AotClosures
{
    /**
     * Every rendered closure's prologue: the signature opening the
     * artifact's factories all share, one spelling.
     */
    private const string PROLOGUE = 'static fn (';

    /**
     * The container's fully-qualified name as the generated code spells
     * it: the artifact is a standalone file with no imports.
     */
    private const string CONTAINER = Container::class;

    /**
     * The method-call lane's one authority, fully qualified.
     */
    private const string INVOCATIONS = MethodCallInvocations::class;

    /**
     * The method-call value object, fully qualified.
     */
    private const string METHOD_CALL = MethodCall::class;

    /**
     * Renders the instance shape's closure: the literal constructor call,
     * its method calls applied when the declaration carries any.
     */
    public static function instance(InstanceDefinition $instanceDefinition): string
    {
        $construction = 'new ' . self::className($instanceDefinition->class) . '('
            . AotArguments::parameterList($instanceDefinition->arguments, $instanceDefinition->id, 'arguments')
            . ')';

        if ($instanceDefinition->calls === []) {
            return self::PROLOGUE . self::CONTAINER . ' $c): object => ' . $construction;
        }

        return self::PROLOGUE . self::CONTAINER . ' $c): object => ' . self::INVOCATIONS
            . '::apply(' . $construction . ', '
            . self::calls($instanceDefinition->calls, $instanceDefinition->id) . ', $c)';
    }

    /**
     * Renders the alias shape's closure: the one delegation hop, spelled
     * exactly as the compiler's own aliasing closure spells it.
     */
    public static function alias(AliasDefinition $aliasDefinition): string
    {
        return self::PROLOGUE . self::CONTAINER . ' $c): mixed => $c->get('
            . var_export($aliasDefinition->target, true) . ')';
    }

    /**
     * Renders the value shape's closure: the held literal, exportable or
     * refused.
     */
    public static function value(ValueDefinition $valueDefinition): string
    {
        return 'static fn (): mixed => ' . AotLiterals::export($valueDefinition->value, $valueDefinition->id, 'value');
    }

    /**
     * @param list<MethodCall> $calls     the declaration's method calls
     * @param string           $serviceId the declaration the calls serve
     */
    private static function calls(array $calls, string $serviceId): string
    {
        $rendered = [];

        foreach ($calls as $call) {
            $rendered[] = 'new ' . self::METHOD_CALL . '(' . var_export($call->method, true) . ', '
                . AotArguments::arrayLiteral($call->arguments, $serviceId, 'calls.' . $call->method)
                . ')';
        }

        return '[' . implode(', ', $rendered) . ']';
    }

    /**
     * @param class-string $class the validated class name
     */
    private static function className(string $class): string
    {
        return mb_ltrim($class, '\\');
    }
}
