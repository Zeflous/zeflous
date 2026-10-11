<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Exception\InvariantViolationException;

/**
 * The result of matching one request against a compiled radix tree.
 *
 * Three outcomes exist, and each carries exactly what its outcome can
 * know:
 *
 * - **found** -- the route and the captured parameters (parameter name =>
 *   raw segment value);
 * - **method not allowed** -- the path matched a route but the method did
 *   not: the sorted, upper-cased methods that route does answer (the
 *   `Allow` answer of an HTTP layer);
 * - **not found** -- no route owns the path.
 *
 * The state-specific accessors ({@see self::route()}, {@see self::parameters()},
 * {@see self::allowedMethods()}) raise an {@see InvariantViolationException}
 * when asked for data the outcome does not hold -- asking is a programming
 * error, not a runtime condition, so it fails loudly instead of returning
 * a misleading null.
 *
 * @api
 */
final readonly class MatchResult
{
    /**
     * @param array<string, string> $parameters
     * @param null|list<string>     $allowedMethods
     */
    private function __construct(
        private ?CompiledRoute $compiledRoute,
        private array $parameters,
        private ?array $allowedMethods,
    ) {
    }

    /**
     * @param array<string, string> $parameters
     */
    public static function found(CompiledRoute $compiledRoute, array $parameters): self
    {
        return new self($compiledRoute, $parameters, null);
    }

    public static function notFound(): self
    {
        return new self(null, [], null);
    }

    /**
     * @param list<string> $allowedMethods
     */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        return new self(null, [], $allowedMethods);
    }

    /**
     * Whether a route was found for the path (and the method).
     */
    public function isFound(): bool
    {
        return $this->compiledRoute instanceof CompiledRoute;
    }

    /**
     * Whether the path matched a route whose methods exclude the request
     * method.
     */
    public function isMethodNotAllowed(): bool
    {
        return $this->allowedMethods !== null;
    }

    /**
     * The matched route.
     */
    public function route(): CompiledRoute
    {
        if (!$this->compiledRoute instanceof CompiledRoute) {
            throw new InvariantViolationException('The match result does not hold a route.');
        }

        return $this->compiledRoute;
    }

    /**
     * The captured parameters of the matched route.
     *
     * @return array<string, string>
     */
    public function parameters(): array
    {
        if (!$this->compiledRoute instanceof CompiledRoute) {
            throw new InvariantViolationException('The match result does not hold parameters.');
        }

        return $this->parameters;
    }

    /**
     * The sorted, upper-cased methods the matched route answers.
     *
     * @return list<string>
     */
    public function allowedMethods(): array
    {
        if ($this->allowedMethods === null) {
            throw new InvariantViolationException('The match result does not hold allowed methods.');
        }

        return $this->allowedMethods;
    }
}
