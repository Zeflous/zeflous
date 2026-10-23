<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Config\Routing\RouteMatchExplanation;

/**
 * The route test command's JSON form: the tool-integration view of
 * one simulated match -- the outcome in the explanation family's own
 * closed spelling, the found route's stable record (the route list's
 * own record shape, {@see RouteListJson}) with its captured
 * parameters, and the explanation's own fields (the failing stage,
 * the narrowing the pipeline performed, the deepest shared prefix,
 * the capped candidate patterns, the allowed methods, the producible
 * media types) -- every field always present, the irrelevant ones
 * spelled as their own empty answers, so a consumer's schema is
 * stable across every outcome.
 */
final readonly class RouteMatchJson
{
    /**
     * One simulated match, encoded as a JSON document.
     */
    public static function rendered(RouteMatchAnswer $routeMatchAnswer): string
    {
        $matchResult = $routeMatchAnswer->matchResult;
        $explanation = $routeMatchAnswer->explanation;
        $found = $routeMatchAnswer->outcome === RouteMatchExplanation::OUTCOME_FOUND;

        return RouteInspectionJson::encode([
            'outcome' => $routeMatchAnswer->outcome,
            'route' => $found ? RouteListJsonRecord::record($matchResult->route()) : null,
            'parameters' => $found ? $matchResult->parameters() : null,
            'explanation' => [
                'failedStage' => $explanation->failedStage(),
                'pathSurvivors' => $explanation->pathSurvivors(),
                'hostSurvivors' => $explanation->hostSurvivors(),
                'versionSurvivors' => $explanation->versionSurvivors(),
                'sharedPrefix' => $explanation->sharedPrefix(),
                'candidatePatterns' => $explanation->neighborPatterns(),
                'allowedMethods' => $explanation->allowedMethods(),
                'producibleMediaTypes' => $explanation->producibleMediaTypes(),
            ],
        ]);
    }
}
