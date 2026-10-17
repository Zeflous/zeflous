<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Framework\Container\Autowiring\Tag;

/** Fixture: several distinct attributes -- the class earns every tag it carries. */
#[Tag('first.tag')]
#[Tag('second.tag')]
final class TwiceTaggedHouse
{
}
