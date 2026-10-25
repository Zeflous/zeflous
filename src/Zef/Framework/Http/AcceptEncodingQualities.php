<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Parses the `Accept-Encoding` header into a coding-to-quality map --
 * the {@see AcceptHeaderParser} grammar's own sibling for the content
 * coding vocabulary (`gzip`, `deflate`, `br`, `identity`, `*`), the
 * same bounded read, the same member walk, the same quality
 * arithmetic.
 *
 * The header is bounded at 8192 bytes before the first byte of
 * parsing, exactly as the media-range parser is: an `Accept-Encoding`
 * line is a handful of short members in every real deployment, and a
 * megabyte spelling is an attacker's probe, not a client's
 * preference. Members that are not a coding token -- empty members,
 * a coding with forbidden characters -- are skipped, not fatal: the
 * legacy engines skip silently and so does the media-range parser,
 * because a refusal here would turn a sloppy client into a failed
 * request over a preference the server can satisfy without it.
 *
 * A coding's quality is the last `q=` parameter that follows it, in
 * the three-decimal grammar the media-range parser accepts; a member
 * with no quality parameter is 1.0. A repeated coding keeps its
 * highest quality -- the same max-fold the media-range parser
 * applies, so `gzip;q=0.1, gzip` reads as 1.0, never as the 0.1 an
 * earlier member wished on it. The parameters may carry the weight
 * grammar's own optional whitespace around the semicolons (`gzip ;
 * q=0.5` is the member `gzip;q=0.5` spells): each parameter is
 * trimmed before the `q=` match and the token before its own match,
 * exactly as the media-range parser trims its own -- while the value
 * stays anchored at the equals sign (`q= 1` is the refusal it always
 * was, because the grammar's OWS lives around the parameter, never
 * inside its value).
 *
 * The map keeps zero qualities and the `identity` and `*` spellings:
 * whether a zero excludes a coding and whether a wildcard accepts one
 * is the negotiator's decision, and a parser that dropped the
 * evidence would have made it silently.
 */
final class AcceptEncodingQualities
{
    /** The header read's byte ceiling. */
    private const int HEADER_LIMIT = 8192;

    /**
     * Parses the header into its coding-to-quality map.
     *
     * @param string $acceptEncoding the raw `Accept-Encoding` line
     *
     * @return array<string, float> the lowercase coding spellings with
     *                              their qualities, first-seen order kept
     *                              by the underlying map
     */
    public static function qualities(string $acceptEncoding): array
    {
        $qualities = [];
        $bounded = mb_substr(mb_strtolower($acceptEncoding), 0, self::HEADER_LIMIT);

        foreach (explode(',', $bounded) as $member) {
            $parsed = self::coding(mb_trim($member));
            if ($parsed === null) {
                continue;
            }

            [$coding, $quality] = $parsed;
            $qualities[$coding] = max($qualities[$coding] ?? 0.0, $quality);
        }

        return $qualities;
    }

    /**
     * @param string $member the `;`-separated member, OWS already
     *                       trimmed around it by the walk
     *
     * @return null|array{string, float} the lowercase coding with its
     *                                   quality, or null when the member is
     *                                   not a coding token
     */
    private static function coding(string $member): ?array
    {
        $token = mb_trim(explode(';', $member)[0]);
        if (preg_match('/^[a-z0-9!#$%&\'*+\-.^_`|~]+$/', $token) !== 1) {
            return null;
        }

        return [$token, self::quality(array_map(trim(...), explode(';', $member)))];
    }

    /**
     * @param list<string> $segments the member's `;`-separated
     *                               parameters, each trimmed of the
     *                               weight grammar's own OWS
     *
     * @return float the last `q=` parameter's value, or 1.0 when the member
     *               carries none
     */
    private static function quality(array $segments): float
    {
        foreach ($segments as $segment) {
            if (!str_starts_with($segment, 'q=')) {
                continue;
            }

            $value = mb_substr($segment, 2);
            if (preg_match('/^(?:0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/D', $value) !== 1) {
                return 0.0;
            }

            return (float) $value;
        }

        return 1.0;
    }
}
