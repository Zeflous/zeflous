<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The explanation of one match against a compiled radix tree: the
 * diagnostic twin of {@see MatchResult} -- the same folds the
 * matcher runs, staged and counted, so a development response can
 * say WHY the table refused a request instead of only THAT it did.
 *
 * The explanation carries the staged narrowing the matcher performs
 * ({@see RouteMatchExplainer} runs the identical pipeline): the
 * number of candidates the path walk settled, the number the host
 * bucket kept, the number the version selection kept, and -- when a
 * stage emptied the set -- which stage it was. A path-stage miss
 * additionally carries the neighborhood: the deepest static prefix
 * the request path still descends into and the route patterns that
 * live under it ({@see RouteMatchNeighborhood}), the "what you
 * probably meant" of a 404. A method-stage miss carries the sorted
 * methods the matched path does answer; an accept-stage miss the
 * media types the matched route does produce -- the same payloads
 * {@see MatchResult} carries for the rendered refusal.
 *
 * The outcome vocabulary is the closed set the router's own metrics
 * and headers spell ({@see self::OUTCOME_*}): found, not_found,
 * method_not_allowed, not_acceptable -- and the stage vocabulary is
 * the pipeline's own five stages ({@see self::STAGE_*}).
 *
 * {@see self::summary()} renders the compact development line: an
 * ASCII, bounded rendering whose every input is clamped or closed
 * (the shared prefix and the neighbor patterns to their own budgets,
 * the control bytes a request path could carry stripped), because
 * the line is destined for a response header and a header is a
 * bounded, line-oriented channel -- the dev surface must not become
 * the reflection surface.
 *
 * @api
 */
final readonly class RouteMatchExplanation
{
    /** The outcome of a match that settled on a route. */
    public const string OUTCOME_FOUND = 'found';

    /** The outcome of a match no route pattern answered. */
    public const string OUTCOME_NOT_FOUND = 'not_found';

    /** The outcome of a match whose path answered other methods. */
    public const string OUTCOME_METHOD_NOT_ALLOWED = 'method_not_allowed';

    /** The outcome of a match whose route produces nothing acceptable. */
    public const string OUTCOME_NOT_ACCEPTABLE = 'not_acceptable';

    /** The stage whose narrowing left no candidate: the path walk. */
    public const string STAGE_PATH = 'path';

    /** The stage whose narrowing left no candidate: the host bucket. */
    public const string STAGE_HOST = 'host';

    /** The stage whose narrowing left no candidate: the version selection. */
    public const string STAGE_VERSION = 'version';

    /** The stage whose narrowing left no candidate: the method answer. */
    public const string STAGE_METHOD = 'method';

    /** The stage whose narrowing left no candidate: the accept negotiation. */
    public const string STAGE_ACCEPT = 'accept';

    /**
     * @param list<string> $neighborPatterns     the patterns under the deepest
     *                                           shared static prefix (path stage)
     * @param list<string> $allowedMethods       the methods a matched path answers
     * @param list<string> $producibleMediaTypes the media types a matched route
     *                                           produces
     */
    private function __construct(
        private string $outcome,
        private ?string $failedStage,
        private int $pathSurvivors,
        private int $hostSurvivors,
        private int $versionSurvivors,
        private string $sharedPrefix,
        private array $neighborPatterns,
        private array $allowedMethods,
        private array $producibleMediaTypes,
    ) {
    }

    /**
     * The explanation of a match that found its route.
     */
    public static function found(int $pathSurvivors, int $hostSurvivors, int $versionSurvivors): self
    {
        return new self(
            self::OUTCOME_FOUND,
            null,
            $pathSurvivors,
            $hostSurvivors,
            $versionSurvivors,
            '',
            [],
            [],
            [],
        );
    }

    /**
     * The explanation of a miss the path walk answered: no route
     * pattern consumed the request path.
     *
     * @param list<string> $neighborPatterns the patterns under the deepest
     *                                       static prefix the path descends into
     */
    public static function pathMiss(
        string $sharedPrefix,
        array $neighborPatterns,
    ): self {
        return new self(
            self::OUTCOME_NOT_FOUND,
            self::STAGE_PATH,
            0,
            0,
            0,
            $sharedPrefix,
            $neighborPatterns,
            [],
            [],
        );
    }

    /**
     * The explanation of a miss the host bucket answered: the path
     * walk settled candidates, and none of their host claims -- nor
     * any host-agnostic fallback -- answered the request host.
     */
    public static function hostMiss(int $pathSurvivors, int $versionSurvivors): self
    {
        return new self(
            self::OUTCOME_NOT_FOUND,
            self::STAGE_HOST,
            $pathSurvivors,
            0,
            $versionSurvivors,
            '',
            [],
            [],
            [],
        );
    }

    /**
     * The explanation of a miss the version selection answered: the
     * path and host kept candidates, and none of them answered the
     * request's explicit API version.
     */
    public static function versionMiss(int $pathSurvivors, int $hostSurvivors): self
    {
        return new self(
            self::OUTCOME_NOT_FOUND,
            self::STAGE_VERSION,
            $pathSurvivors,
            $hostSurvivors,
            0,
            '',
            [],
            [],
            [],
        );
    }

    /**
     * The explanation of a refusal the method answer gave: the path
     * matched, and the methods it answers exclude the request's own.
     *
     * @param list<string> $allowedMethods
     */
    public static function methodRefusal(
        array $allowedMethods,
        int $pathSurvivors,
        int $hostSurvivors,
        int $versionSurvivors,
    ): self {
        return new self(
            self::OUTCOME_METHOD_NOT_ALLOWED,
            self::STAGE_METHOD,
            $pathSurvivors,
            $hostSurvivors,
            $versionSurvivors,
            '',
            [],
            $allowedMethods,
            [],
        );
    }

    /**
     * The explanation of a refusal the accept negotiation gave: the
     * route answers the method, and produces none of the
     * representations the request accepts.
     *
     * @param list<string> $producibleMediaTypes
     */
    public static function acceptRefusal(
        array $producibleMediaTypes,
        int $pathSurvivors,
        int $hostSurvivors,
        int $versionSurvivors,
    ): self {
        return new self(
            self::OUTCOME_NOT_ACCEPTABLE,
            self::STAGE_ACCEPT,
            $pathSurvivors,
            $hostSurvivors,
            $versionSurvivors,
            '',
            [],
            [],
            $producibleMediaTypes,
        );
    }

    /**
     * The outcome of the explained match -- the same answer
     * {@see MatchResult} carries for the same inputs.
     */
    public function outcome(): string
    {
        return $this->outcome;
    }

    /**
     * Whether the explained match found its route.
     */
    public function isFound(): bool
    {
        return $this->outcome === self::OUTCOME_FOUND;
    }

    /**
     * The stage whose narrowing refused the match -- the pipeline's
     * own name for the failing fold, or null when the match found
     * its route.
     */
    public function failedStage(): ?string
    {
        return $this->failedStage;
    }

    /**
     * The candidates the path walk settled, the candidates the host
     * bucket kept, and the candidates the version selection kept:
     * the narrowing the pipeline performed, counted.
     */
    public function pathSurvivors(): int
    {
        return $this->pathSurvivors;
    }

    /**
     * The candidates the host bucket kept.
     */
    public function hostSurvivors(): int
    {
        return $this->hostSurvivors;
    }

    /**
     * The candidates the version selection kept.
     */
    public function versionSurvivors(): int
    {
        return $this->versionSurvivors;
    }

    /**
     * The deepest static prefix the request path descends into --
     * carried by a path-stage miss, empty for every other outcome.
     */
    public function sharedPrefix(): string
    {
        return $this->sharedPrefix;
    }

    /**
     * The route patterns under the deepest shared static prefix --
     * carried by a path-stage miss, empty for every other outcome.
     *
     * @return list<string>
     */
    public function neighborPatterns(): array
    {
        return $this->neighborPatterns;
    }

    /**
     * The methods the matched path answers -- carried by a
     * method-stage refusal.
     *
     * @return list<string>
     */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }

    /**
     * The media types the matched route produces -- carried by an
     * accept-stage refusal.
     *
     * @return list<string>
     */
    public function producibleMediaTypes(): array
    {
        return $this->producibleMediaTypes;
    }

    /**
     * The compact development line: the bounded rendering
     * {@see RouteMatchSummary} spells from this explanation -- the
     * outcome's stage, the narrowing the pipeline performed, and the
     * payload the failing stage carries, every free-form input
     * clamped to its own budget and stripped of control bytes,
     * because the line is destined for a response header.
     */
    public function summary(): string
    {
        return RouteMatchSummary::render($this);
    }

    /**
     * The outcome a match result settled on, in this vocabulary's
     * own closed spellings -- the fold the span's status and the
     * meter's dimensions both read, so the vocabulary has one owner.
     */
    public static function outcomeOf(MatchResult $matchResult): string
    {
        return match (true) {
            $matchResult->isFound() => self::OUTCOME_FOUND,
            $matchResult->isMethodNotAllowed() => self::OUTCOME_METHOD_NOT_ALLOWED,
            $matchResult->isNotAcceptable() => self::OUTCOME_NOT_ACCEPTABLE,
            default => self::OUTCOME_NOT_FOUND,
        };
    }
}
