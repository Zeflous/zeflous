<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\View;

use Override;
use Zef\Framework\View\ViewEngine;
use Zef\Framework\View\ViewName;

/**
 * A recording stand-in for the rendering port: every render it
 * answers is remembered with the name's own spelling and the bag it
 * carried, and the answer itself comes from the constructor's map --
 * so a face or scope test pins the questions it asked without
 * composing a whole real engine.
 *
 * @internal
 */
final class RecordingViewEngine implements ViewEngine
{
    /** @var list<array{name: string, data: array<string, mixed>}> every render asked, in ask order */
    public array $renders = [];

    /** @param array<string, string> $answers the canned answers, keyed by the name's own spelling */
    public function __construct(
        private readonly array $answers = [],
    ) {
    }

    #[Override]
    public function render(ViewName $viewName, array $data): string
    {
        $this->renders[] = ['name' => $viewName->spelling(), 'data' => $data];

        return $this->answers[$viewName->spelling()] ?? '';
    }
}
