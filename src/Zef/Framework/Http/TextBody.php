<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The plain-text document's home in the PSR-7 parsed body: the
 * contract's triple is array, object or null ({@see ParsedBody}), so a
 * scalar text document cannot ride the parsed body as itself -- the
 * string it IS would be rejected by the request seam's own guard.
 *
 * Wrapping the text in a named value object is the honest legal shape:
 * `text/plain` bodies the family owns deserialize into a `TextBody`,
 * and a controller reads `$request->getParsedBody()->text` instead of
 * draining the request's stream. The object is final and readonly with
 * one public property -- no behaviour, no invariants, nothing to
 * defend; the family's strategy ({@see TextBodyParser}) constructs it
 * and the parsed body carries it.
 */
final readonly class TextBody
{
    /**
     * @param string $text the request body's text, verbatim
     */
    public function __construct(
        public string $text,
    ) {
    }
}
