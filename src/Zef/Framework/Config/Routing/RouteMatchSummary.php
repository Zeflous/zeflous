<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Renders the route match explanation's bounded development line:
 * the compact header spelling of {@see RouteMatchExplanation} --
 * the outcome's stage, the narrowing the pipeline performed, and
 * the payload the failing stage carries.
 *
 * Every free-form input is clamped or closed, because the line is
 * destined for a response header and a header is a bounded,
 * line-oriented channel: the shared prefix and the neighbor
 * patterns to their own character budgets (multi-byte text clamped
 * by characters, never byte-sliced), the control bytes a request
 * path could carry stripped, and the closed vocabularies (stage
 * names, method names) rendered as-is -- the dev surface must not
 * become the reflection surface.
 *
 * @internal
 */
final class RouteMatchSummary
{
    /** The budget the shared-prefix rendering clamps to. */
    private const int PREFIX_BUDGET = 128;

    /** The budget each candidate rendering clamps to. */
    private const int CANDIDATE_BUDGET = 128;

    /** The marker a clamped rendering carries. */
    private const string CLAMP_MARKER = '...';

    /**
     * Renders the explanation's bounded development line.
     */
    public static function render(RouteMatchExplanation $routeMatchExplanation): string
    {
        $tokens = [
            'stage=' . ($routeMatchExplanation->failedStage() ?? 'none'),
            \sprintf(
                'survivors=path:%d,host:%d,version:%d',
                $routeMatchExplanation->pathSurvivors(),
                $routeMatchExplanation->hostSurvivors(),
                $routeMatchExplanation->versionSurvivors(),
            ),
        ];

        if ($routeMatchExplanation->sharedPrefix() !== '') {
            $tokens[] = 'prefix=' . self::clamped($routeMatchExplanation->sharedPrefix(), self::PREFIX_BUDGET);
        }

        if ($routeMatchExplanation->neighborPatterns() !== []) {
            $tokens[] = 'candidates=' . implode(',', array_map(
                static fn (string $pattern): string => self::clamped($pattern, self::CANDIDATE_BUDGET),
                $routeMatchExplanation->neighborPatterns(),
            ));
        }

        if ($routeMatchExplanation->allowedMethods() !== []) {
            $tokens[] = 'allowed=' . implode(',', $routeMatchExplanation->allowedMethods());
        }

        if ($routeMatchExplanation->producibleMediaTypes() !== []) {
            $tokens[] = 'producible=' . implode(',', array_map(
                static fn (string $mediaType): string => self::clamped($mediaType, self::CANDIDATE_BUDGET),
                $routeMatchExplanation->producibleMediaTypes(),
            ));
        }

        return implode(' ', $tokens);
    }

    /**
     * One rendering's own clamp: the control bytes a header refuses
     * stripped, the character budget honored, and the marker spelled
     * when the clamp cut.
     */
    private static function clamped(string $value, int $budget): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        $clean = \is_string($clean) ? $clean : '';

        if (mb_strlen($clean) <= $budget) {
            return $clean;
        }

        return mb_substr($clean, 0, $budget) . self::CLAMP_MARKER;
    }
}
