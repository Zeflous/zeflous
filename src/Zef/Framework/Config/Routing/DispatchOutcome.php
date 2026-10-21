<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Exception\InvariantViolationException;

/**
 * The result of dispatching one request through the routing runtime's
 * dispatcher.
 *
 * Three outcomes exist, and each carries exactly what its outcome can
 * know (the pattern of {@see MatchResult}):
 *
 * - **executed** -- the matched route, the captured parameters the handler
 *   received, and whatever the handler returned;
 * - **method not allowed** -- the path matched a route whose methods
 *   exclude the request method: the sorted, upper-cased methods that route
 *   does answer;
 * - **not found** -- no route owns the path, so nothing was executed.
 *
 * The state-specific accessors ({@see self::route()}, {@see self::parameters()},
 * {@see self::result()}, {@see self::allowedMethods()}) raise an
 * {@see InvariantViolationException} when asked for data the outcome does
 * not hold -- asking is a programming error, not a runtime condition, so it
 * fails loudly instead of returning a misleading null.
 *
 * @api
 */
final readonly class DispatchOutcome
{
    /**
     * @param array<string, string> $parameters
     * @param null|list<string>     $allowedMethods
     */
    private function __construct(
        private ?CompiledRoute $compiledRoute,
        private array $parameters,
        private mixed $result,
        private ?array $allowedMethods,
        private ?array $acceptableMediaTypes,
    ) {
    }

    /**
     * @param array<string, string> $parameters
     */
    public static function executed(CompiledRoute $compiledRoute, array $parameters, mixed $result): self
    {
        return new self($compiledRoute, $parameters, $result, null, null);
    }

    public static function notFound(): self
    {
        return new self(null, [], null, null, null);
    }

    /**
     * @param list<string> $allowedMethods
     */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        return new self(null, [], null, $allowedMethods, null);
    }

    /**
     * Creates a 406 outcome without executing a handler.
     *
     * @param list<string> $acceptableMediaTypes
     */
    public static function notAcceptable(array $acceptableMediaTypes): self
    {
        return new self(null, [], null, null, $acceptableMediaTypes);
    }

    /**
     * Whether content negotiation rejected every representation.
     */
    public function isNotAcceptable(): bool
    {
        return $this->acceptableMediaTypes !== null;
    }

    /**
     * The media types the route supports.
     *
     * @return list<string>
     */
    public function acceptableMediaTypes(): array
    {
        if ($this->acceptableMediaTypes === null) {
            throw new InvariantViolationException('The dispatch outcome does not hold acceptable media types.');
        }

        return $this->acceptableMediaTypes;
    }

    /**
     * Whether a handler ran for the request.
     */
    public function isExecuted(): bool
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
     * The route whose handler ran.
     */
    public function route(): CompiledRoute
    {
        if (!$this->compiledRoute instanceof CompiledRoute) {
            throw new InvariantViolationException('The dispatch outcome does not hold a route.');
        }

        return $this->compiledRoute;
    }

    /**
     * The captured parameters the handler received.
     *
     * @return array<string, string>
     */
    public function parameters(): array
    {
        if (!$this->compiledRoute instanceof CompiledRoute) {
            throw new InvariantViolationException('The dispatch outcome does not hold parameters.');
        }

        return $this->parameters;
    }

    /**
     * Whatever the handler returned.
     */
    public function result(): mixed
    {
        if (!$this->compiledRoute instanceof CompiledRoute) {
            throw new InvariantViolationException('The dispatch outcome does not hold a result.');
        }

        return $this->result;
    }

    /**
     * The sorted, upper-cased methods the matched route answers.
     *
     * @return list<string>
     */
    public function allowedMethods(): array
    {
        if ($this->allowedMethods === null) {
            throw new InvariantViolationException('The dispatch outcome does not hold allowed methods.');
        }

        return $this->allowedMethods;
    }
}
