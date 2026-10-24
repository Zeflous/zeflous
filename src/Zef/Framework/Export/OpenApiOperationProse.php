<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The enrichment's prose grammar: the one question both declaration
 * spellings route through -- is this summary, this description, this
 * tag list a shape the document can carry?
 *
 * The grammar is deliberately tight and fail-closed: prose is
 * non-empty, printable, and bounded (an empty summary is a defect
 * the export refuses, not an absence it invents content for), tags
 * are a list of non-empty printable strings, and every refusal names
 * the route whose declaration failed -- the same posture every
 * declaration in the table already receives. The fold exists so
 * {@see OpenApiOperationEnrichment} can stay a single-question class
 * under the architecture gate's difficulty ceiling: the grammar is
 * the machinery both of its readers need, nothing more.
 *
 * @internal consumed through {@see OpenApiOperationEnrichment}
 */
final readonly class OpenApiOperationProse
{
    /** The prose fields' shared byte ceiling: summaries and descriptions are prose, but bounded prose. */
    private const int FIELD_LIMIT = 2048;

    private const string CONTROL_BYTES = '/[\x00-\x1f\x7f]/';

    /**
     * @return null|non-empty-string
     *
     * @throws ExportException when the prose is present but empty, unprintable, or over the ceiling
     */
    public static function optional(mixed $value, string $field, CompiledRoute $compiledRoute): ?string
    {
        if ($value === null) {
            return null;
        }

        if (
            !\is_string($value)
            || $value === ''
            || mb_strlen($value) > self::FIELD_LIMIT
            || preg_match(self::CONTROL_BYTES, $value) === 1
        ) {
            throw new ExportException(
                \sprintf(
                    'The route "%s" OpenAPI %s must be non-empty, printable, and at most %d bytes.',
                    $compiledRoute->path()->pattern(),
                    $field,
                    self::FIELD_LIMIT,
                ),
            );
        }

        return $value;
    }

    /**
     * @return list<non-empty-string>
     *
     * @throws ExportException when the tags are not a list of non-empty printable strings
     */
    public static function tags(mixed $tags, CompiledRoute $compiledRoute): array
    {
        if (!\is_array($tags) || array_is_list($tags) === false) {
            throw new ExportException(
                \sprintf(
                    'The route "%s" OpenAPI tags must be a list of strings.',
                    $compiledRoute->path()->pattern(),
                ),
            );
        }

        $validated = [];

        foreach ($tags as $tag) {
            if (
                !\is_string($tag)
                || $tag === ''
                || mb_strlen($tag) > self::FIELD_LIMIT
                || preg_match(self::CONTROL_BYTES, $tag) === 1
            ) {
                throw new ExportException(
                    \sprintf(
                        'The route "%s" OpenAPI tags must be non-empty printable strings.',
                        $compiledRoute->path()->pattern(),
                    ),
                );
            }

            $validated[] = $tag;
        }

        return $validated;
    }
}
