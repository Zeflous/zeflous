<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Http\ObservabilityFamilies;
use Zef\Framework\Http\TimingMiddleware;
use Zef\Framework\Http\TimingPolicy;

/**
 * The timing middleware's config-driven construction seam: the
 * factory the framework profile's own `observability.timing` service
 * registration names.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * TimingFactory::class . '::fromConfig'` over the `framework.config`
 * service argument -- resolved through the container's existing
 * declarative grammar, exactly as the four security families' are.
 * This class is the grammar's string-addressed target, which is why
 * it is marked `@api` rather than being baselined or suppressed.
 *
 * Validation is the family's own ({@see TimingPolicy::fromMap()}):
 * every knob read strictly, the unknown family and the unknown knob
 * each a named halt. There is deliberately no fail-loud seam beyond
 * the reading -- the family has no runtime material to be without
 * (no secret, no token table), so there is no posture the boot must
 * refuse: the one safety decision the family owns, the header's
 * disclosure, is the shipped-off default itself, and a deployment
 * that flips it does so as an explicit, validated knob write.
 *
 * @api
 */
final readonly class TimingFactory
{
    /**
     * Builds the middleware from the frozen repository's
     * `observability.timing` subtree.
     */
    public static function fromConfig(Config $config): TimingMiddleware
    {
        return new TimingMiddleware(
            TimingPolicy::fromMap(
                ObservabilityFamilies::subtree($config->get('observability'), 'timing'),
            ),
        );
    }
}
