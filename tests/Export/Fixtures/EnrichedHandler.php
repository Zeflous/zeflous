<?php

declare(strict_types=1);

namespace Zef\Test\Export\Fixtures;

use Zef\Framework\Export\OpenApiOperationAttribute;

/**
 * The OpenAPI document tests' attribute-bearing handler: a class whose
 * method carries the export family's first-party operation attribute
 * exactly as a host's controller would -- the enrichment source the
 * reflection path reads over a `Class@method` route handler.
 *
 * @internal
 */
final class EnrichedHandler
{
    #[OpenApiOperationAttribute(
        summary: 'One enriched operation',
        description: 'The handler method this attribute decorates is the route the document enriches.',
        tags: ['Fixture', 'Enrichment'],
    )]
    public function show(): string
    {
        return 'enriched';
    }

    /**
     * The un-annotated sibling: a method the reflection reads and
     * finds no declaration on -- the honest absence, not an error.
     */
    public function plain(): string
    {
        return 'plain';
    }

    /**
     * The doubly-annotated sibling: a method whose two declarations
     * are the ambiguity the export refuses with the route named --
     * one declaration per operation, the table's own duplicate
     * refusal replayed.
     */
    #[OpenApiOperationAttribute(summary: 'The first declaration')]
    #[OpenApiOperationAttribute(summary: 'The second declaration')] // @phpstan-ignore-line
    public function twice(): string
    {
        return 'twice';
    }
}
