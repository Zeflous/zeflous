<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The container's own reserved service identifiers: the read-side
 * services the boot registers ahead of the definitions (so a host's
 * same-identifier entry is an explicit rebind that wins) -- spelled
 * here, in the layer that registers them, with the kernel's
 * {@see \Zef\Framework\FrameworkDefaults} delegating to them so both
 * spellings answer the same identifier.
 */
final readonly class ContainerServices
{
    /**
     * The tags lane's read side: the boot-built locator.
     */
    public const string TAGS_SERVICE = 'framework.tags';

    /**
     * The compile/dump lane's boot answer: which lane answered, why,
     * and what the compile saw.
     */
    public const string AOT_SERVICE = 'framework.aot';
}
