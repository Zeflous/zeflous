<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The OpenAPI parameter objects one route's path parameters spell:
 * every `{name}` template variable the pattern declares becomes one
 * `in: path` parameter, `required` by the specification's own reading
 * (a path template variable either binds or the path does not match),
 * in the pattern's own path order.
 *
 * The schema is the engine's own declaration translated, never
 * re-derived: the parameter's constraint pattern
 * ({@see \Zef\Framework\Config\Routing\RouteConstraints::pattern()})
 * renders as the JSON Schema `pattern` the route's author wrote,
 * wrapped in the anchors the engine's whole-segment semantics spells
 * (`^(?:...)$` -- the closest ECMA-262 translation of the engine's
 * `\z` anchoring, a documented approximation: the pattern itself is
 * PCRE as declared, and a spelling ECMA-262 rejects is a spelling the
 * route table already owns). A parameter without a constraint renders
 * as the honest `{"type": "string"}` -- the engine accepts any value,
 * and the schema says exactly that.
 */
final readonly class OpenApiParameters
{
    /**
     * The parameter objects for one route, in path order.
     *
     * @param CompiledRoute $compiledRoute the route whose path parameters are rendered
     *
     * @return list<array<string, mixed>> one OpenAPI Parameter Object per path parameter
     */
    public static function fromRoute(CompiledRoute $compiledRoute): array
    {
        $parameters = [];

        foreach ($compiledRoute->path()->parameterNames() as $name) {
            $schema = ['type' => 'string'];

            $pattern = $compiledRoute->constraints()->pattern($name);
            if ($pattern !== null) {
                $schema['pattern'] = '^(?:' . $pattern . ')$';
            }

            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => $schema,
            ];
        }

        return $parameters;
    }
}
