<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use Override;

/**
 * Raised when a matched path does not answer the request method
 * (ROADMAP.md, "Error Handling System", phase 1: the exception
 * hierarchy) -- the method refusal (405).
 *
 * Carries the refusal's evidence: the method that was asked, the path
 * that matched, and every method that path does answer -- the payload
 * the error response's `Allow` header is built from, so the mapper
 * never re-derives what the matcher already resolved
 * ({@see \Zef\Framework\Config\Routing\RouteMethodAnswer} computes it
 * from the leaf's candidates; the legacy's
 * `MethodNotAllowedException` carried the same three fields).
 *
 * The allowed-methods list is validated here, the value-object
 * discipline of the declaration grammars: a method refusal that names
 * no allowed method is a defect (the leaf matched at least one route by
 * construction), and an entry that is not a non-empty string is a
 * malformed signal -- both are refused rather than silently repaired.
 *
 * @api the typed family's published refusal vocabulary: hosts catch these
 *      across their own code, and the router lane's throw site -- the
 *      dispatch translation in
 *      {@see \Zef\Framework\Middleware\RouteDispatchHandler} that
 *      consumes the method/path payload -- raises it for every matched
 *      path that does not answer the request method.
 */
final class MethodNotAllowedException extends ZefException implements CarriesHttpStatus
{
    /**
     * Every method the matched path answers, in the order given.
     *
     * @var list<non-empty-string>
     */
    public readonly array $allowedMethods;

    /**
     * @param string                  $method         the method the request asked
     * @param string                  $path           the path that matched
     * @param array<array-key, mixed> $allowedMethods every method the matched path answers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        array $allowedMethods,
    ) {
        $this->allowedMethods = $this->validated($allowedMethods);

        parent::__construct("Method '{$method}' is not allowed for {$path}.");
    }

    #[Override]
    public function httpStatus(): int
    {
        return 405;
    }

    /**
     * Judges the allowed-methods list every method refusal must carry:
     * at least one method, every entry a non-empty string.
     *
     * @param array<array-key, mixed> $allowedMethods
     *
     * @return list<non-empty-string>
     */
    private function validated(array $allowedMethods): array
    {
        if ($allowedMethods === []) {
            throw new InvariantViolationException(
                'A method-not-allowed refusal must name at least one allowed method.',
            );
        }

        $validated = [];
        foreach ($allowedMethods as $allowedMethod) {
            if (!\is_string($allowedMethod) || $allowedMethod === '') {
                throw new InvariantViolationException(
                    'An allowed method must be a non-empty string, '
                    . get_debug_type($allowedMethod) . ' given.',
                );
            }

            $validated[] = $allowedMethod;
        }

        return $validated;
    }
}
