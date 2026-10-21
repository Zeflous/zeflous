<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Stringable;
use Zef\Framework\Config\ConfigException;

/**
 * Parses route host patterns and canonicalises untrusted request host values.
 *
 * Route patterns support exact DNS labels and whole-label parameters such as
 * "{tenant}.example.com". Ports, IP literals, partial-label parameters and
 * wildcard spellings are rejected so matching has one deterministic grammar.
 *
 * @internal
 */
final class RouteHost
{
    /**
     * Validates and canonicalises a DNS host pattern.
     *
     * @return non-empty-string
     */
    public static function canonical(string $host): string
    {
        if ($host === '' || mb_strlen($host) > 253) {
            throw self::invalid($host);
        }

        $labels = explode('.', $host);
        $canonical = [];
        $seenParameters = [];

        foreach ($labels as $label) {
            if (preg_match('/\A\{([a-zA-Z][a-zA-Z0-9_]{0,63})\}\z/D', $label, $matches) === 1) {
                $parameter = mb_strtolower($matches[1]);
                if (isset($seenParameters[$parameter])) {
                    throw self::invalid($host);
                }

                $seenParameters[$parameter] = true;
                $canonical[] = '{' . $parameter . '}';

                continue;
            }

            if (preg_match('/\A[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\z/D', $label) !== 1) {
                throw self::invalid($host);
            }

            $canonical[] = mb_strtolower($label);
        }

        if (\count($canonical) > 127) {
            throw self::invalid($host);
        }

        return self::canonicalisedHost(implode('.', $canonical), $host);
    }

    /**
     * Canonicalises untrusted request input without turning it into a config error.
     */
    public static function request(?string $host): ?string
    {
        if ($host === null) {
            return null;
        }

        try {
            if (str_contains($host, '{')) {
                return null;
            }

            return self::canonical($host);
        } catch (ConfigException) {
            return null;
        }
    }

    /**
     * Matches a canonical route pattern to a request host.
     *
     * @return null|array<string, string> null means mismatch; an empty map is an exact match
     */
    public static function parameters(string $pattern, ?string $requestHost): ?array
    {
        if ($requestHost === null) {
            return null;
        }

        $patternLabels = explode('.', $pattern);
        $hostLabels = explode('.', $requestHost);

        if (\count($patternLabels) !== \count($hostLabels)) {
            return null;
        }

        $parameters = [];

        foreach ($patternLabels as $index => $patternLabel) {
            if (preg_match('/\A\{([a-z][a-z0-9_]{0,63})\}\z/D', $patternLabel, $matches) === 1) {
                $parameters[$matches[1]] = $hostLabels[$index];

                continue;
            }

            if ($patternLabel !== $hostLabels[$index]) {
                return null;
            }
        }

        return $parameters;
    }

    /**
     * Renders a host pattern with validated DNS-label parameter values.
     *
     * @param array<string, mixed> $parameters
     */
    public static function render(string $pattern, array $parameters): string
    {
        foreach (self::parameterNames($pattern) as $name) {
            if (!\array_key_exists($name, $parameters)) {
                throw new ConfigException(\sprintf('The host parameter "%s" is required.', $name));
            }

            $value = $parameters[$name];
            if (!\is_scalar($value) && !$value instanceof Stringable) {
                throw new ConfigException(\sprintf('The host parameter "%s" must be a DNS label value.', $name));
            }

            $label = (string) $value;
            if (preg_match('/\A[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\z/D', $label) !== 1) {
                throw new ConfigException(\sprintf('The host parameter "%s" must be a valid DNS label.', $name));
            }

            $pattern = str_replace('{' . $name . '}', mb_strtolower($label), $pattern);
            unset($parameters[$name]);
        }

        return $pattern;
    }

    /**
     * Whether two host patterns can match at least one identical DNS hostname.
     */
    public static function overlaps(string $left, string $right): bool
    {
        $leftLabels = explode('.', $left);
        $rightLabels = explode('.', $right);

        if (\count($leftLabels) !== \count($rightLabels)) {
            return false;
        }

        foreach ($leftLabels as $index => $leftLabel) {
            $rightLabel = $rightLabels[$index];
            $leftParameter = str_starts_with($leftLabel, '{');
            $rightParameter = str_starts_with($rightLabel, '{');

            if (!$leftParameter && !$rightParameter && $leftLabel !== $rightLabel) {
                return false;
            }
        }

        return true;
    }

    /**
     * Number of literal labels; a larger number is more specific.
     */
    public static function specificity(string $pattern): int
    {
        return \count(array_filter(
            explode('.', $pattern),
            static fn (string $label): bool => !str_starts_with($label, '{'),
        ));
    }

    /**
     * @return list<string>
     */
    public static function parameterNames(string $pattern): array
    {
        preg_match_all('/\{([a-z][a-z0-9_]{0,63})\}/', $pattern, $matches);

        return $matches[1];
    }

    /**
     * Narrows the joined host to a non-empty string, the shape companion of
     * {@see RouteEntryCollections::nonEmptyString()}:
     * the label grammar above canonicalises a non-empty host through
     * non-empty labels, so the join is belt-and-braces guarded here rather
     * than trusted, and the refusal keeps the ordinary invalid-host message.
     *
     * @return non-empty-string
     */
    private static function canonicalisedHost(string $canonicalHost, string $host): string
    {
        if ($canonicalHost === '') {
            throw self::invalid($host);
        }

        return $canonicalHost;
    }

    private static function invalid(string $host): ConfigException
    {
        return new ConfigException(
            \sprintf('The route host "%s" must be an exact DNS hostname without a port or wildcard, or use whole-label parameters.', $host),
        );
    }
}
