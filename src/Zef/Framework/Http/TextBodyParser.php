<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;

/**
 * The plain-text strategy: `text/plain`, deserialized into the
 * {@see TextBody} value object -- the PSR contract's parsed body is
 * array, object or null, and a bare string would be rejected by the
 * request seam's own guard ({@see ParsedBody}), so the text rides the
 * only legal shape it has.
 *
 * The strategy is deliberately the family's simplest member: no
 * grammar to fail (`text/plain` carries no document structure), no
 * engine ceiling to read, and the body arrives non-empty and
 * budget-bounded from the middleware's own gates. A controller that
 * typed its input as `TextBody` reads `.text`; a controller that
 * prefers the raw stream simply does not compose the family for text
 * -- the family's ownership is the host's declaration.
 */
final readonly class TextBodyParser implements BodyParserInterface
{
    #[Override]
    public function supports(string $mediaType): bool
    {
        return strcasecmp($mediaType, 'text/plain') === 0;
    }

    #[Override]
    public function parse(string $body): TextBody
    {
        return new TextBody($body);
    }
}
