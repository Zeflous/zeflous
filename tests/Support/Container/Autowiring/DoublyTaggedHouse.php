<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Framework\Container\Autowiring\Tag;

/** Fixture: the same attribute twice -- the collision the boot refuses. */
#[Tag('dup.tag')]
#[Tag('dup.tag')]
final class DoublyTaggedHouse
{
}
