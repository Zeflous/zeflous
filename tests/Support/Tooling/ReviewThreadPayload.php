<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Parses a GitHub GraphQL `reviewThreads` payload into a
 * {@see ReviewThreadReport}.
 *
 * The parser is fail-closed: a payload that is not valid JSON, or that does
 * not carry the expected `reviewThreads.nodes` shape (see
 * {@see ReviewThreadNodes}), raises a {@see ToolingException} rather than
 * being treated as "no threads" -- a silent empty result would let the lane
 * believe a blocked pull request has nothing to resolve.
 *
 * Unusable nodes are counted rather than aborted, so one malformed
 * thread can never hide the others -- and it can never silently
 * vanish either: GitHub legitimately answers null entries for
 * deleted or permission-restricted threads, and a thread the sweep
 * cannot account for still counts as unresolved for the required
 * check, so every unusable node surfaces in the report's own count
 * ({@see ReviewThreadReport::$unusable}) and rides the resolution's
 * failure total ({@see ReviewThreadResolution}).
 */
final readonly class ReviewThreadPayload
{
    private const string INVALID_JSON = 'Review-thread payload is not valid JSON.';

    public function __construct(private string $json)
    {
    }

    public function report(): ReviewThreadReport
    {
        $decoded = json_decode($this->json, true);

        if (!\is_array($decoded)) {
            throw new ToolingException(self::INVALID_JSON);
        }

        $threads = [];
        $unusable = 0;

        foreach (new ReviewThreadNodes($decoded)->toList() as $node) {
            $thread = ReviewThreadFactory::fromNode($node);

            if (!$thread instanceof ReviewThread) {
                ++$unusable;

                continue;
            }

            $threads[] = $thread;
        }

        return new ReviewThreadReport($threads, $unusable);
    }
}
