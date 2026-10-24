<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Http\CompressionMiddleware;
use Zef\Framework\Http\CompressionPolicy;
use Zef\Framework\Http\HttpFamilies;

/**
 * The compression middleware's config-driven construction seam: the
 * factory the framework profile's own `http.compression` service
 * registration names.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * CompressionFactory::class . '::fromConfig'` over the `framework.config`
 * service argument -- resolved through the container's existing
 * declarative grammar, exactly as the security and observability
 * families' are. This class is the grammar's string-addressed target,
 * which is why it is marked `@api` rather than being baselined or
 * suppressed: it is consumed through the configuration the boot
 * installs, not through a statically visible call.
 *
 * Validation is the family's own ({@see CompressionPolicy::fromMap()}
 * over the `http.compression` subtree the {@see HttpFamilies} census
 * reads): every knob read strictly, the unknown family and the
 * unknown knob each a named halt. The middleware is deliberately NOT
 * a member of the shipped `middleware.stack`: the stack's membership
 * is the host's declaration, exactly as it is for the timing and
 * security families -- the registration ahead of the composition is
 * what lets a host add `http.compression` to its stack as a knob
 * write, not a service definition.
 *
 * @api
 */
final readonly class CompressionFactory
{
    /**
     * Builds the middleware from the frozen repository's
     * `http.compression` subtree.
     */
    public static function fromConfig(Config $config): CompressionMiddleware
    {
        return new CompressionMiddleware(
            CompressionPolicy::fromMap(
                HttpFamilies::subtree($config->get('http'), 'compression'),
            ),
        );
    }
}
