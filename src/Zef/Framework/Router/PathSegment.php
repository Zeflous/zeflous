<?php

declare(strict_types=1);

namespace Zef\Framework\Router;

/**
 * One segment of a parsed route path: either literal text or a parameter
 * placeholder.
 *
 * A route path is a slash-separated list of segments, and every segment is
 * exactly one of two things: static text (matched character for character)
 * or a `{name}` parameter that captures one whole segment (see
 * {@see RoutePath::parse()} for the grammar). A parameter can never share a
 * segment with text -- `/users/{id}` is a `users` static segment followed by
 * an `id` parameter segment, while `/users{id}` is malformed -- because a
 * parameter boundary and a slash boundary must agree for a captured value to
 * stay decodable.
 *
 * The two kinds are only constructible through the named factories, so a
 * segment cannot exist in a state the grammar never produces. `value()` is
 * the single accessor for both kinds: the literal text of a static segment,
 * the name of a parameter segment.
 */
final readonly class PathSegment
{
    private function __construct(
        private string $value,
        private bool $parameter,
    ) {
    }

    /**
     * A static segment matched literally against the request path.
     */
    public static function static(string $text): self
    {
        return new self($text, false);
    }

    /**
     * A `{name}` parameter segment that captures one whole request segment.
     */
    public static function parameter(string $name): self
    {
        return new self($name, true);
    }

    /**
     * Whether this segment is a `{name}` parameter (false: static text).
     */
    public function isParameter(): bool
    {
        return $this->parameter;
    }

    /**
     * The literal text (static segment) or the parameter name (parameter
     * segment).
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * The parameter-blind rendering: a parameter segment renders as `{}`,
     * a static segment as its literal text.
     *
     * {@see RoutePath::canonical()} joins these with slashes; this is the
     * piece the segment itself owns, so the spelling can never disagree
     * with the kind.
     */
    public function canonical(): string
    {
        return $this->parameter ? '{}' : $this->value;
    }
}
