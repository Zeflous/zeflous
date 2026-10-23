<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The inspection commands' JSON encoding: the one spelling every JSON
 * view renders with -- pretty-printed for the operator's terminal,
 * forward slashes unescaped (route patterns are full of them), and
 * throw-on-error so an encoding failure surfaces as the CLI's own
 * refusal instead of a silent `false`.
 */
final readonly class RouteInspectionJson
{
    private const int FLAGS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR;

    /**
     * @param array<mixed> $data the view's own data shape
     *
     * @return non-empty-string the encoded document
     */
    public static function encode(array $data): string
    {
        return json_encode($data, self::FLAGS);
    }
}
