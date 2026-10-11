<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The resource macro: one DSL declaration that expands into a
 * resource's full CRUD route table (roadmap "DSL Layer", rule D).
 *
 * Seven entries come out, in table order: `index`, `create`, `store`,
 * `show`, `edit`, `update` (one entry answering both `PUT` and `PATCH`)
 * and `destroy`. Each carries the handler `Controller@action` and the
 * name `<base>.<action>`, where the base is the resource path's static
 * segments joined with dots -- the parameters a resource path declares
 * (`/users/{user}/posts`) shape its routes but not its name.
 *
 * The only parameter the macro creates is `{id}`, so the only constraint
 * it accepts is `id`; anything else is a typo that fails closed. The
 * expansion registers through the façade's own public surface, so
 * prefix composition, scope middleware, and every declaration-time
 * validation apply to the generated routes exactly as to hand-written
 * ones.
 *
 * @internal
 */
final class RouteResourceMacro
{
    /**
     * The one parameter the macro's routes declare.
     */
    private const string ID_PARAMETER = 'id';

    /**
     * The path suffix that declares the `{id}` parameter.
     */
    private const string ID_SUFFIX = '/{id}';

    /**
     * The action table: HTTP methods, path suffix, and whether the
     * entry's path carries the `{id}` parameter (and can be constrained).
     */
    private const array ACTIONS = [
        ['action' => 'index', 'methods' => ['GET'], 'suffix' => '', 'constrained' => false],
        ['action' => 'create', 'methods' => ['GET'], 'suffix' => '/create', 'constrained' => false],
        ['action' => 'store', 'methods' => ['POST'], 'suffix' => '', 'constrained' => false],
        ['action' => 'show', 'methods' => ['GET'], 'suffix' => self::ID_SUFFIX, 'constrained' => true],
        ['action' => 'edit', 'methods' => ['GET'], 'suffix' => self::ID_SUFFIX . '/edit', 'constrained' => true],
        ['action' => 'update', 'methods' => ['PUT', 'PATCH'], 'suffix' => self::ID_SUFFIX, 'constrained' => true],
        ['action' => 'destroy', 'methods' => ['DELETE'], 'suffix' => self::ID_SUFFIX, 'constrained' => true],
    ];

    /**
     * Expands a resource declaration into its seven routes on the DSL
     * session in force.
     *
     * @param array<array-key, mixed> $constraints the `id` constraint, if any
     */
    public static function expand(RouteDsl $routeDsl, string $path, string $controller, array $constraints): void
    {
        if ($controller === '') {
            throw new ConfigException('The resource controller must not be empty.');
        }

        self::assertOnlyIdConstraints($constraints);

        $nameBase = RouteResourceName::base($path);

        foreach (self::ACTIONS as $action) {
            $draft = $routeDsl->match(
                $action['methods'],
                $path . $action['suffix'],
                $controller . '@' . $action['action'],
            );
            $draft->name($nameBase . '.' . $action['action']);

            if (!$action['constrained'] || $constraints === []) {
                continue;
            }

            $draft->where($constraints);
        }
    }

    /**
     * A resource constraint map may only carry the `id` parameter -- the
     * only parameter the macro ever declares.
     *
     * @param array<array-key, mixed> $constraints
     */
    private static function assertOnlyIdConstraints(array $constraints): void
    {
        foreach (array_keys($constraints) as $name) {
            if ($name !== self::ID_PARAMETER) {
                throw new ConfigException(
                    'The resource constraints must only carry the "id" parameter.',
                );
            }
        }
    }
}
