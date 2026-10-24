<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The shipped family's manifest: the four standard strategies in
 * declaration order, over one policy -- the construction half the
 * negotiation table's {@see RequestBodyParsers::standard()} reads.
 *
 * A host-built table never consults this manifest: the manifest is
 * what the SHIPPED family looks like (JSON first, then XML, the
 * url-encoded form, plain text -- grammars disjoint, the order
 * declaration hygiene), and a host that knows its media types better
 * spells its own list at the table's constructor. The JSON strategy
 * alone reads the policy (the engine's own recursion ceiling); the
 * other three strategies carry no knobs of their own.
 */
final class StandardBodyParsers
{
    /**
     * The shipped strategies, in declaration order.
     *
     * @return list<BodyParserInterface> the four standard parsers
     */
    public static function family(BodyParserPolicy $bodyParserPolicy): array
    {
        return [
            new JsonBodyParser($bodyParserPolicy->jsonDepth),
            new XmlBodyParser(),
            new FormBodyParser(),
            new TextBodyParser(),
        ];
    }
}
