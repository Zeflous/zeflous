<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\Container;
use Zef\Framework\Container\ServiceDefinition;

/**
 * The product type a factory-shaped declaration can prove -- the pure
 * static form alone answers (see {@see AutowireImplementations} for
 * the collection this serves).
 *
 * The spelling `'Target::method'` proves its product's type only when
 * the target is an existing class that no declared or container
 * identifier shadows (the precedence that turns the spelling into an
 * instance-method call whose product the spelling cannot name) and
 * the method is static with a single non-built-in return type
 * ({@see AutowireStaticReturn} reads it). Every other spelling
 * answers null: not inferable, never guessed.
 */
final readonly class AutowireFactoryProduct
{
    /**
     * @param array<string, ServiceDefinition> $declaredById the run's declared set, by identifier
     */
    public static function type(string $factory, array $declaredById, Container $container): ?string
    {
        [$target, $method] = explode('::', $factory);

        if (!class_exists($target) || isset($declaredById[$target]) || $container->has($target)) {
            return null;
        }

        return AutowireStaticReturn::of($target, $method);
    }
}
