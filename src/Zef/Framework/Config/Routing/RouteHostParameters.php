<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Host-parameter semantics: capturing parameters when a canonical pattern
 * matches a request host, and rendering a pattern's parameters back into a
 * host for reverse routing.
 *
 * @internal
 */
final class RouteHostParameters
{
    private const string LABEL_PARAMETER = '/\A\{([a-z][a-z0-9_]{0,63})\}\z/D';

    private const string LABEL_NAMES = '/\{([a-z][a-z0-9_]{0,63})\}/';

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

        return self::labelledParameters($patternLabels, $hostLabels);
    }

    /**
     * The whole-label parameter names a host pattern declares, in order.
     *
     * @return list<string>
     */
    public static function parameterNames(string $pattern): array
    {
        preg_match_all(self::LABEL_NAMES, $pattern, $matches);

        return $matches[1];
    }

    /**
     * Zips the whole-label parameters a label-count-matching host captured.
     *
     * @param list<string> $patternLabels the pattern's labels
     * @param list<string> $hostLabels    the request host's labels
     *
     * @return null|array<string, string> null means a static label mismatched
     */
    private static function labelledParameters(array $patternLabels, array $hostLabels): ?array
    {
        $parameters = [];

        foreach ($patternLabels as $index => $patternLabel) {
            if (preg_match(self::LABEL_PARAMETER, $patternLabel, $matches) === 1) {
                $parameters[$matches[1]] = $hostLabels[$index];

                continue;
            }

            if ($patternLabel !== $hostLabels[$index]) {
                return null;
            }
        }

        return $parameters;
    }
}
