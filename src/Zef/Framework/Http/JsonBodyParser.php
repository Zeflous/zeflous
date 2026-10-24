<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use JsonException;
use Override;

/**
 * The JSON strategy: `application/json` and its vendor members under
 * the structured `+json` suffix ({@see MediaTypeSuffix}), deserialized
 * into the array the PSR contract's parsed body carries.
 *
 * The grammar is the replay lane's own house spelling
 * ({@see ReplayBodyDecoder::isJsonMediaType()}), the decode is the
 * engine's own with associative arrays -- the depth knob the family
 * reads ({@see BodyParserPolicy::$jsonDepth}) is the engine's own
 * recursion ceiling, and the default (32) mirrors the replay decoder's
 * documented posture. Two honest refusals, both fail-closed:
 *
 * - a malformed document answers {@see MalformedBodyException} with the
 *   engine's own `JsonException` riding as the previous throwable --
 *   the attribution survives for whoever logs the refusal;
 * - a well-formed document whose top level is a scalar (`5`, `"text"`,
 *   `true`) answers the same refusal: the PSR contract's parsed body is
 *   array, object or null, and an associative decode folds every legal
 *   JSON document into an array EXCEPT the scalars -- a scalar is not a
 *   shape the parsed body can legally carry ({@see ParsedBody}), and
 *   silently nulling it would tell the controller "no body" about a
 *   body that arrived (KDD-BP5, `docs/ci-notes/http-body-parser.md`).
 *
 * A well-formed `null` document answers null -- the PSR triple's own
 * third member, and the honest answer for a JSON document that
 * declares the absence of any payload.
 */
final readonly class JsonBodyParser implements BodyParserInterface
{
    /**
     * @param int<1, 512> $jsonDepth the engine recursion ceiling the decode
     *                               asks for ({@see BodyParserPolicy}; the
     *                               corridor the config seam enforces)
     */
    public function __construct(
        private int $jsonDepth = 32,
    ) {
    }

    #[Override]
    public function supports(string $mediaType): bool
    {
        return strcasecmp($mediaType, 'application/json') === 0
            || MediaTypeSuffix::underApplication($mediaType, '+json');
    }

    #[Override]
    public function parse(string $body): ?array
    {
        try {
            /** @var mixed $decoded the engine's associative decode */
            $decoded = json_decode($body, true, $this->jsonDepth, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new MalformedBodyException(
                'The request body is not a well-formed JSON document: '
                . $jsonException->getMessage(),
                0,
                $jsonException,
            );
        }

        if (\is_array($decoded)) {
            // @var array<array-key, mixed> $decoded
            return $decoded;
        }

        if ($decoded === null) {
            return null;
        }

        throw new MalformedBodyException(
            "The JSON document's top level is a scalar, which the parsed body"
            . ' cannot carry: ' . get_debug_type($decoded) . ' given.',
        );
    }
}
