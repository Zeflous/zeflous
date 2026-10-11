<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

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
 * Each pattern is stored fully anchored (`^(?:pattern)$`): a constraint
 * matches a whole captured segment or it does not match at all, so
 * `[0-9]+` never lets `12a` through. At match time {@see self::check()}
 * is the single seam -- one anchored `preg_match` per captured value, and
 * `true` when the parameter carries no constraint at all.
 *
 * @api
 */
final readonly class RouteConstraints
{
    /**
     * @param array<string, non-empty-string> $patterns anchored patterns per parameter name
     */
    private function __construct(private array $patterns)
    {
    }

    /**
     * Validates a constraint map against the route's parsed path.
     *
     * @param array<string, non-empty-string> $constraints parameter name => pattern
     */
    public static function fromMap(array $constraints, RoutePath $routePath): self
    {
        $names = array_fill_keys($routePath->parameterNames(), true);
        $patterns = [];

        foreach ($constraints as $name => $pattern) {
            if (!isset($names[$name])) {
                throw new ConfigException(
                    \sprintf(
                        'The route pattern "%s" constrains the unknown parameter "%s".',
                        $routePath->pattern(),
                        $name,
                    ),
                );
            }

            $anchored = '/^(?:' . $pattern . ')$/';

            if (!self::isCompilable($anchored)) {
                throw new ConfigException(
                    \sprintf(
                        'The route pattern "%s" has an invalid constraint pattern for the parameter "%s".',
                        $routePath->pattern(),
                        $name,
                    ),
                );
            }

            $patterns[$name] = $anchored;
        }

        return new self($patterns);
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

    /**
     * Probes whether a pattern compiles. The engine-level diagnostic the
     * invalid pattern raises is converted into the probe's answer: an
     * invalid pattern is an expected outcome here, not an error to report,
     * so the scoped handler throws a marker the catch turns into `false`.
     *
     * @param non-empty-string $pattern
     */
    private static function isCompilable(string $pattern): bool
    {
        set_error_handler(static function (): never {
            throw new ConfigException('The constraint pattern does not compile.');
        });

        try {
            return preg_match($pattern, '') !== false;
        } catch (ConfigException) {
            return false;
        } finally {
            restore_error_handler();
        }
    }
}
