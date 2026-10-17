<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Attribute;

/**
 * The concrete-binding attribute for an interface-typed parameter (the
 * autowiring lane's "mapping interface -> implementasi" without leaving
 * the declaration).
 *
 * `#[Target(FileLogger::class)]` on an `Logger $logger` parameter tells
 * the planner exactly which concrete class satisfies the type: the
 * referenced class is verified instantiable and then planned under its
 * own name -- registered, already birthed, or birthed now -- exactly as a
 * concrete-typed parameter would be. The attribute accepts classes,
 * interfaces and enums at construction (the reference itself is only
 * *named* here); the planner refuses a non-instantiable target with the
 * parameter in the message, because a binding that cannot produce an
 * instance is a declaration defect, not a runtime surprise.
 *
 * `#[Target]` does not combine with {@see Inject} (one parameter, one
 * binding intent) and does not ride a variadic parameter (collections
 * are the tags lane's concern).
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Target
{
    public function __construct(
        public string $class,
    ) {
        if (!class_exists($class) && !interface_exists($class) && !enum_exists($class)) {
            throw new AutowiringException(
                "#[Target] references the unknown class, interface or enum '{$class}'.",
            );
        }
    }
}
