<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/** A variadic parameter cannot carry #[Value] (a collection is not one value). */
final class VariadicValueHouse
{
    /** @var list<string> */
    public array $tags;

    public function __construct(
        #[Value('app.tags')]
        string ...$tags,
    ) {
        $this->tags = array_values($tags);
    }
}
