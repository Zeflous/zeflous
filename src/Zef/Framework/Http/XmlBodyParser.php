<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use LibXMLError;
use Override;
use SimpleXMLElement;

/**
 * The XML strategy: `application/xml`, `text/xml` and the vendor
 * members under the structured `+xml` suffix ({@see MediaTypeSuffix}),
 * deserialized into the {@see SimpleXMLElement} the PSR contract's
 * parsed body carries -- an object, the triple's second member.
 *
 * The engine seam is SimpleXML over libxml, probed at construction and
 * injectable exactly as the compression family's capability probe is
 * ({@see CompressionCapabilities}): a build without the extension can
 * still construct the family, and the refusal fires only when an XML
 * body actually arrives -- a deployment that never receives XML never
 * pays for the extension it lacks. That refusal is fail-closed
 * {@see HttpException}, deliberately NOT the brotli posture of a
 * graceful bypass: compression had a substitute coding to negotiate
 * down to, XML has none, and a silent no-parse would hand the
 * controller a null parsed body about a body the family claims to own
 * -- the exact misconfiguration trap the knob census exists to refuse
 * (KDD-BP3, `docs/ci-notes/http-body-parser.md`).
 *
 * The XXE posture is the engine's own hardening, kept: no
 * `LIBXML_NOENT` (external entities are never substituted into the
 * tree), `LIBXML_NONET` on every parse (no network fetch a DTD or
 * entity reference could trigger), and no `LIBXML_PARSEHUGE` (the
 * libxml depth, text-node and entity-amplification ceilings stay in
 * force). An entity-bearing document that tries to resolve an external
 * reference fails the parse and answers {@see MalformedBodyException}
 * like any other malformed body (KDD-BP4).
 *
 * The internal-errors mode is process-global engine state, so the
 * parse restores the prior mode in a finally: a persistent worker
 * never observes a leaked diagnostic mode it did not ask for, and the
 * error queue is drained on every path so no stale libxml error ever
 * leaks into an unrelated later parse.
 */
final readonly class XmlBodyParser implements BodyParserInterface
{
    private bool $simpleXmlPresent;

    /**
     * @param null|bool $simpleXmlPresent whether the engine's SimpleXML seam
     *                                    is available; null (the default)
     *                                    asks the runtime probe, so the
     *                                    extension-absent refusal is
     *                                    pinnable without uninstalling an
     *                                    extension
     */
    public function __construct(?bool $simpleXmlPresent = null)
    {
        $this->simpleXmlPresent = $simpleXmlPresent ?? \function_exists('simplexml_load_string');
    }

    #[Override]
    public function supports(string $mediaType): bool
    {
        return strcasecmp($mediaType, 'application/xml') === 0
            || strcasecmp($mediaType, 'text/xml') === 0
            || MediaTypeSuffix::underApplication($mediaType, '+xml');
    }

    #[Override]
    public function parse(string $body): SimpleXMLElement
    {
        if (!$this->simpleXmlPresent) {
            throw new HttpException(
                'The runtime cannot parse XML request bodies: the SimpleXML'
                . ' extension is not available in this PHP build.',
            );
        }

        $priorMode = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = simplexml_load_string($body, options: \LIBXML_NONET);

            if ($document === false) {
                throw new MalformedBodyException(
                    'The request body is not a well-formed XML document:'
                    . $this->detail(libxml_get_errors()),
                );
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($priorMode);
        }

        return $document;
    }

    /**
     * The refusal's detail: the engine's first own diagnostic for the
     * failed parse, trimmed and spaced behind the colon -- or nothing
     * when the queue the parse left carries no error at all.
     *
     * @param list<LibXMLError> $errors the queue the failed parse left
     */
    private function detail(array $errors): string
    {
        return $errors === [] ? '' : ' ' . mb_trim($errors[0]->message);
    }
}
