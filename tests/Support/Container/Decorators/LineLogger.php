<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Decorators;

/**
 * The decoration lane's logger contract: one method whose answer is a
 * deterministic string, so a wrapper chain's layering is observable in
 * the answer alone.
 */
interface LineLogger
{
    public function line(string $text): string;
}
