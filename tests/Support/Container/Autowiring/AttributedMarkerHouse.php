<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Framework\Container\Autowiring\Tag;

/** Fixture: the attribute form -- and an attribute a rule can collide with. */
#[Tag('marked.tag')]
final class AttributedMarkerHouse implements MarkerA
{
}
