<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Attribute;
use Zef\Framework\Container\ServiceTags;

/**
 * The class-level tag attribute (the autoconfiguration lane's
 * attribute form; ROADMAP, "CONTAINER" area 3 -- metadata declared
 * through PHP attributes on the class).
 *
 * A class-shaped declaration whose class carries `#[Tag('console.command')]`
 * joins that tag at boot, without the declaration spelling a `tags` list
 * -- the tag rides the class, the declaration names the class, and the
 * configuration a host does not repeat is the configuration the roadmap
 * promised to save.
 *
 * The grammar is the tags lane's own ({@see ServiceTags}):
 * one spelling, matched against the same vocabulary every declared tag
 * must match -- an attribute the grammar refuses is the declaration
 * defect it is, surfaced at the read with the attribute's own message.
 *
 * The attribute may repeat on one class -- a class earns as many
 * tags as it carries, each attribute one -- and the same tag twice is
 * the collision the boot refuses (two sources claiming one
 * declaration is an ambiguity to surface, not a precedence to
 * guess). Combining the attribute with a `tags` key that spells the
 * same tag is that same collision: declare the tag once, from one
 * source.
 *
 * The attribute belongs to the DECLARED class only: a class the
 * declared set never names carries its tags unheard (this lane
 * discovers nothing -- the declaration, not the filesystem, is the
 * source of what boots).
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Tag
{
    /**
     * The constructor's caller is the reflection API itself:
     * `ReflectionAttribute::newInstance()` (the autoconfiguration
     * lane's read), so the tag's own grammar validation runs at the
     * read -- a caller static analysis cannot see, and the psalm
     * baseline's unused verdict records honestly.
     */
    public function __construct(
        public string $tag,
    ) {
        ServiceTags::assert($tag);
    }
}
