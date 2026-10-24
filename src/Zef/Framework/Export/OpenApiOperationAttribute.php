<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use Attribute;

/**
 * The export family's first-party operation declaration: the attribute
 * a handler's own method carries when the route table's structural
 * truth is not the whole documentation -- a summary a reader sees
 * first, a description the operation is known by, the tags that group
 * it in the rendered UI.
 *
 * The attribute is read at EXPORT TIME ONLY ({@see OpenApiOperationEnrichment}
 * reflects the handler a `Class@method` route names): the runtime
 * never sees it, the container never loads it, and a handler without
 * it answers exactly as it always did -- the enrichment is opt-in
 * documentation over a table that is already complete without it.
 *
 * Nothing is validated here: an attribute's arguments cannot be
 * constrained by the engine that instantiates them, so the enrichment
 * fold judges the shapes when it reads ({@see self::$summary} is
 * non-empty and printable when present, {@see self::$tags} a list of
 * non-empty strings), and a wrong shape refuses the export with the
 * route's own identity -- the same fail-closed reading every
 * declaration in the table already receives.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class OpenApiOperationAttribute
{
    /**
     * @param null|string  $summary     one line the operation is known by, or null when the route carries none
     * @param null|string  $description the operation's own prose, or null when the route carries none
     * @param list<string> $tags        the groupings the operation renders under
     */
    public function __construct(
        public ?string $summary = null,
        public ?string $description = null,
        public array $tags = [],
    ) {
    }
}
