<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

/**
 * The OpenAPI document's own identity: the `info` object every document
 * MUST carry -- a title and a version, both required by the
 * specification and neither of them derivable from the route table
 * (the table knows shapes and names, not the API's name or where it
 * stands in its release history).
 *
 * The grammar is deliberately tight and fail-closed: non-empty,
 * printable, bounded strings -- an empty title or a control byte in a
 * version is a caller's defect, and the export refuses it with the
 * field named rather than shipping a document tools quietly reject.
 * The OpenAPI version field is a free-form string (the specification
 * recommends semantic versioning but does not require it), so the
 * grammar judges shape, not semantics -- the same posture the route
 * table's own free-form fields carry.
 */
final readonly class OpenApiInfo
{
    /** The identity fields' shared byte ceiling: a title or version is metadata, not prose. */
    private const int FIELD_LIMIT = 256;

    private const string CONTROL_BYTES = '/[\x00-\x1f\x7f]/';

    /**
     * @param non-empty-string $title
     * @param non-empty-string $version
     */
    private function __construct(
        private string $title,
        private string $version,
    ) {
    }

    /**
     * @param string $title   the API's name, as the caller declares it
     * @param string $version the API's own version string (not the OpenAPI specification version)
     *
     * @throws ExportException when either field is empty, carries a control byte, or exceeds the ceiling
     */
    public static function fromFields(string $title, string $version): self
    {
        return new self(
            self::validated($title, 'info.title'),
            self::validated($version, 'info.version'),
        );
    }

    /**
     * The `info` object as the document carries it.
     *
     * @return array{title: non-empty-string, version: non-empty-string}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'version' => $this->version];
    }

    /**
     * @return non-empty-string
     *
     * @throws ExportException when the field fails the grammar
     */
    private static function validated(string $value, string $field): string
    {
        if ($value === '' || mb_strlen($value) > self::FIELD_LIMIT || preg_match(self::CONTROL_BYTES, $value) === 1) {
            throw new ExportException(
                \sprintf(
                    'The OpenAPI %s must be non-empty, printable, and at most %d bytes.',
                    $field,
                    self::FIELD_LIMIT,
                ),
            );
        }

        return $value;
    }
}
