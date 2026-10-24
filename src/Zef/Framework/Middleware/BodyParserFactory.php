<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Http\BodyParserMiddleware;
use Zef\Framework\Http\BodyParserPolicy;
use Zef\Framework\Http\HttpFamilies;

/**
 * The body-parser middleware's config-driven construction seam: the
 * factory the framework profile's own `http.body_parser` service
 * registration names.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * BodyParserFactory::class . '::fromConfig'` over the `framework.config`
 * service argument -- resolved through the container's existing
 * declarative grammar, exactly as the compression family's is
 * ({@see CompressionFactory}). This class is the grammar's
 * string-addressed target, which is why it is marked `@api` rather
 * than being baselined or suppressed: it is consumed through the
 * configuration the boot installs, not through a statically visible
 * call.
 *
 * Validation is the family's own ({@see BodyParserPolicy::fromMap()}
 * over the `http.body_parser` subtree the {@see HttpFamilies} census
 * reads): every knob read strictly, the unknown family and the
 * unknown knob each a named halt. The middleware is deliberately NOT
 * a member of the shipped `middleware.stack`: the stack's membership
 * is the host's declaration, exactly as it is for the timing,
 * security and compression families -- the registration ahead of the
 * composition is what lets a host add `http.body_parser` to its stack
 * as a knob write, not a service definition.
 *
 * @api
 */
final readonly class BodyParserFactory
{
    /**
     * Builds the middleware from the frozen repository's
     * `http.body_parser` subtree.
     */
    public static function fromConfig(Config $config): BodyParserMiddleware
    {
        return new BodyParserMiddleware(
            BodyParserPolicy::fromMap(
                HttpFamilies::subtree($config->get('http'), 'body_parser'),
            ),
        );
    }
}
