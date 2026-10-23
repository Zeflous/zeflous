<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Config\Routing\RouteMatchExplanation;

/**
 * The route test command's text form: the operator's view of one
 * simulated match -- the outcome's own closed spelling first (the
 * exit code mirrors it: 0 the found answer, 1 every refusal the
 * table answered), then the found route's own record with its
 * captured parameters, or the refusing stage's own payload: the
 * narrowing the pipeline performed, the deepest shared prefix and
 * the capped candidate patterns for a path miss, the allowed methods
 * for a 405, the producible media types for a 406.
 *
 * Pure presentation over {@see RouteMatchAnswer}'s pair, reading the
 * engines' own readers -- never a second judgement of the match.
 */
final readonly class RouteMatchText
{
    /**
     * One simulated match, spelled for the terminal.
     */
    public static function rendered(RouteMatchAnswer $routeMatchAnswer): string
    {
        $matchResult = $routeMatchAnswer->matchResult;
        $explanation = $routeMatchAnswer->explanation;

        $lines = ['match ' . $routeMatchAnswer->outcome];

        if ($routeMatchAnswer->outcome === RouteMatchExplanation::OUTCOME_FOUND) {
            $route = $matchResult->route();

            $lines[] = '  route:       ' . ($route->name() ?? '(unnamed)');
            $lines[] = '  pattern:     ' . $route->path()->pattern();

            if ($route->host() !== null) {
                $lines[] = '  host:        ' . $route->host();
            }

            $lines[] = '  methods:     ' . implode(',', $route->methods());
            $lines[] = '  handler:     ' . $route->handler();

            foreach ($matchResult->parameters() as $name => $value) {
                $lines[] = '  parameter:   ' . $name . ' = ' . $value;
            }

            return implode("\n", $lines) . "\n";
        }

        $failedStage = $explanation->failedStage();

        if ($failedStage !== null) {
            $lines[] = '  stage:       ' . $failedStage;
        }

        $lines[] = '  survivors:   path ' . $explanation->pathSurvivors()
            . ', host ' . $explanation->hostSurvivors()
            . ', version ' . $explanation->versionSurvivors();

        if ($explanation->sharedPrefix() !== '') {
            $lines[] = '  prefix:      ' . $explanation->sharedPrefix();
        }

        foreach ($explanation->neighborPatterns() as $pattern) {
            $lines[] = '  candidate:   ' . $pattern;
        }

        if ($explanation->allowedMethods() !== []) {
            $lines[] = '  allowed:     ' . implode(',', $explanation->allowedMethods());
        }

        if ($explanation->producibleMediaTypes() !== []) {
            $lines[] = '  producible:  ' . implode(',', $explanation->producibleMediaTypes());
        }

        return implode("\n", $lines) . "\n";
    }
}
