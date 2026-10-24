<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The parameter constraints of one route, validated and anchored at
 * compile time.
 *
 * A constraint is declared as a regular expression for one parameter name
 * (`['id' => '[0-9]+']`). Registration is the moment to judge it, not the
 * match: a constraint naming a parameter the pattern does not contain, or
 * a pattern the engine cannot compile, fails the whole route table with a
 * {@see ConfigException} (see `ROADMAP.md`, "Configuration System + DSL +
 * Radix Tree" -- constraint validation happens "saat registrasi, bukan
 * saat runtime").
 *
 * Each pattern is stored fully anchored (`^(?:pattern)\z`): a constraint
 * matches a whole captured segment or it does not match at all, so
 * `[0-9]+` never lets `12a` through -- and never lets `12\n` through
 * either, the one value a `$` anchor would wave past itself (a `$`
 * matches before a trailing newline, an end the captured value did
 * not reach). At match time {@see self::check()} is the single seam
 * -- one anchored `preg_match` per captured value, and `true` when
 * the parameter carries no constraint at all. The read side answers
 * the same truth for tooling: {@see self::pattern()} hands the
 * declared pattern (verbatim, unanchored) to the export folds, so an
 * OpenAPI parameter schema renders the route's own constraint -- the
 * engine's declaration, never a re-derivation.
 *
 * @api
 */
final readonly class RouteConstraints
{
    /**
     * @param array<string, non-empty-string> $patterns anchored patterns per parameter name
     * @param array<string, non-empty-string> $declared the declared pattern per parameter name, verbatim
     */
    private function __construct(private array $patterns, private array $declared)
    {
    }

    /**
     * Validates a constraint map against the route's parsed path.
     *
     * @param array<string, non-empty-string> $constraints parameter name => pattern
     */
    public static function fromMap(array $constraints, RoutePath $routePath): self
    {
        [$patterns, $declared] = RouteConstraintDeclarations::validated($constraints, $routePath);

        return new self($patterns, $declared);
    }

    /**
     * The declared constraint pattern for one parameter, verbatim and
     * unanchored, or null when the parameter carries no constraint:
     * the read-side seam the ecosystem export renders from (an
     * OpenAPI parameter schema's `pattern` is the route's own
     * declaration, spelled as its author wrote it).
     *
     * @param string $name the route parameter's name
     *
     * @return null|non-empty-string
     */
    public function pattern(string $name): ?string
    {
        return $this->declared[$name] ?? null;
    }

    /**
     * Whether a captured segment value satisfies the parameter's
     * constraint; a parameter without a constraint accepts any value.
     */
    public function check(string $name, string $value): bool
    {
        if (!isset($this->patterns[$name])) {
            return true;
        }

        return preg_match($this->patterns[$name], $value) === 1;
    }
}
