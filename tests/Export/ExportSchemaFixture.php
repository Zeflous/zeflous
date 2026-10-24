<?php

declare(strict_types=1);

namespace Zef\Test\Export;

use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Errors\ValidationError;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The export family's meta-schema harness: the official interchange
 * schemas the generated documents are validated against, vendored as
 * pinned fixtures and loaded offline.
 *
 * The OpenAPI 3.1 schema is the OpenAPI Initiative's own
 * `schema/2022-10-07` artifact, pinned by SHA-256: the document the
 * specification itself publishes, not a hand-written subset. The
 * Postman Collection schema is Postman's own v2.1.0 draft-07
 * artifact, pinned the same way. Validation runs on the JSON
 * round-trip of the generated document (the artifact the export
 * actually ships), never on the PHP array shape, and through the
 * specification-compliant validator ({@see CompliantValidator}): the
 * plain `Validator` APPLIES the schema's `default` values into the
 * data while validating, and the OAS parameter object then fails its
 * own `unevaluatedProperties` on the values opis just injected.
 *
 * The OpenAPI schema needs ONE mechanical rewrite at load: its four
 * `{"$dynamicRef": "#meta"}` Schema-Object references
 * (components.schemas, parameter.schema, media-type.schema,
 * header.schema) rely on the 2020-12 rule that a `$dynamicAnchor`
 * also serves as a static `$anchor` when no dynamic scope carries
 * the name -- and opis/json-schema 2.6 implements the dynamic walk
 * but not that fallback, resolving the fragment to the document root
 * instead. The rewrite replaces each of the four with the statically
 * equivalent `{"$ref": "#/$defs/schema"}` -- the same target the
 * specification's own fallback selects, over the byte-identical
 * official artifact the pin asserts. The Postman schema loads
 * verbatim.
 *
 * @internal
 */
final readonly class ExportSchemaFixture
{
    /** The official OpenAPI 3.1 schema artifact's pinned digest. */
    private const string OAS_SHA256 = 'da01ba28852cac0de53893797cb8d1942bc3b05084f526dcc216717dec314ed0';

    /** The official Postman v2.1.0 draft-07 schema artifact's pinned digest. */
    private const string POSTMAN_SHA256 = '90a81dbfd6aae2b1605f5d987d08a2bdab51a68c2a027ecffc52b11656ef7d8b';

    /**
     * A validator over the two official schemas, offline.
     */
    public static function validator(): CompliantValidator
    {
        return new CompliantValidator();
    }

    /**
     * The official OpenAPI 3.1 schema, pinned and mechanically
     * rewritten as the class record spells.
     *
     * @return stdClass the schema document, as the validator consumes it
     */
    public static function openApiSchema(): stdClass
    {
        $path = __DIR__ . '/../Export/schema/oas-3.1-schema-2022-10-07.json';
        $raw = (string) file_get_contents($path);

        TestCase::assertSame(
            self::OAS_SHA256,
            hash('sha256', $raw),
            'The vendored OpenAPI 3.1 schema artifact changed.',
        );

        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        self::rewriteMetaReferences($decoded);

        $encoded = json_encode($decoded, \JSON_THROW_ON_ERROR);
        $schema = json_decode($encoded, false, 512, \JSON_THROW_ON_ERROR);

        TestCase::assertInstanceOf(stdClass::class, $schema);

        return $schema;
    }

    /**
     * The official Postman Collection v2.1.0 schema, pinned and loaded verbatim.
     *
     * @return stdClass the schema document, as the validator consumes it
     */
    public static function postmanSchema(): stdClass
    {
        $path = __DIR__ . '/../Export/schema/postman-collection-v2.1.0-draft-07.json';
        $raw = (string) file_get_contents($path);

        TestCase::assertSame(
            self::POSTMAN_SHA256,
            hash('sha256', $raw),
            'The vendored Postman schema artifact changed.',
        );

        $schema = json_decode($raw, false, 512, \JSON_THROW_ON_ERROR);

        TestCase::assertInstanceOf(stdClass::class, $schema);

        return $schema;
    }

    /**
     * The generated document's own JSON round-trip: the artifact the
     * export ships, decoded back -- the honest validation target.
     *
     * @param array<mixed> $document the export family's data shape
     *
     * @return stdClass the document as its JSON form spells it
     */
    public static function roundTrip(array $document): stdClass
    {
        $encoded = json_encode($document, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);

        $decoded = json_decode($encoded, false, 512, \JSON_THROW_ON_ERROR);

        TestCase::assertInstanceOf(stdClass::class, $decoded);

        return $decoded;
    }

    /**
     * The deepest error's human-readable sentence: the leaf keyword,
     * its arguments, and the data path it judged -- the honest
     * failure message a meta-schema refusal reports.
     */
    public static function leafSentence(ValidationError $validationError): string
    {
        $leaf = $validationError;

        while (true) {
            $subErrors = $leaf->subErrors();

            if ($subErrors === []) {
                break;
            }

            $next = $subErrors[0];

            if (!$next instanceof ValidationError) {
                break;
            }

            $leaf = $next;
        }

        $args = $leaf->args();
        $path = $leaf->data()->path();
        $argsEncoded = json_encode($args);
        $pathEncoded = json_encode($path);

        return \sprintf(
            'keyword "%s", args %s, at data path %s',
            $leaf->keyword(),
            \is_string($argsEncoded) ? $argsEncoded : '<unencodable>',
            \is_string($pathEncoded) ? $pathEncoded : '<unencodable>',
        );
    }

    /**
     * The mechanical rewrite: every bare `{"$dynamicRef": "#meta"}`
     * node becomes its statically equivalent `{"$ref":
     * "#/$defs/schema"}` -- the target the specification's own
     * fallback selects when no dynamic scope carries the anchor.
     *
     * @param mixed $node the decoded schema, by reference
     */
    private static function rewriteMetaReferences(mixed &$node): void
    {
        if (!\is_array($node)) {
            return;
        }

        if (isset($node['$dynamicRef']) && $node['$dynamicRef'] === '#meta' && \count($node) === 1) {
            $node = ['$ref' => '#/$defs/schema'];
        }

        foreach ($node as &$child) {
            self::rewriteMetaReferences($child);
        }
    }
}
