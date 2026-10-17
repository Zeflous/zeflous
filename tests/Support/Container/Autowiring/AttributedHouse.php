<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Framework\Container\Autowiring\Tag;

/** Fixture: the attribute form alone -- the class earns its tag at boot. */
#[Tag('made.tag')]
final class AttributedHouse
{
}
