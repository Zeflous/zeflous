<?php

declare(strict_types=1);

namespace Zef\Framework\Contracts;

use Psr\Container\ContainerInterface;

/**
 * Registration unit of the ZEF Module System (see `ROADMAP.md`).
 *
 * A provider declares services on the container. Providers are registered once
 * during kernel boot and must be side-effect free beyond that registration.
 */
interface ServiceProviderInterface
{
    /**
     * Register services on the given container.
     */
    public function register(ContainerInterface $container): void;
}
