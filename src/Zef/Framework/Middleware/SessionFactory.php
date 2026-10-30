<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Adapters\Session\FileSessionAdapter;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Http\HttpFamilies;
use Zef\Framework\Http\SessionMiddleware;
use Zef\Framework\Http\SessionPolicy;
use Zef\Framework\Http\SessionStores;

/**
 * The session middleware's config-driven construction seam: the
 * factory the framework profile's own `http.session` service
 * registration names.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * SessionFactory::class . '::fromConfig'` over the `framework.config`
 * service argument -- resolved through the container's existing
 * declarative grammar, exactly as the body-parser family's is
 * ({@see BodyParserFactory}). This class is the grammar's
 * string-addressed target, which is why it is marked `@api` rather
 * than being baselined or suppressed: it is consumed through the
 * configuration the boot installs, not through a statically visible
 * call.
 *
 * Validation is the family's own ({@see SessionPolicy::fromMap()}
 * over the `http.session` subtree the {@see HttpFamilies} census
 * reads): every knob read strictly, the unknown family and the
 * unknown knob each a named halt. The middleware is deliberately NOT
 * a member of the shipped `middleware.stack`: the stack's membership
 * is the host's declaration, exactly as it is for the compression
 * and body-parser families -- the registration ahead of the
 * composition is what lets a host add `http.session` to its stack as
 * a knob write, not a service definition.
 *
 * The storage driver is the composition seam's own question, the
 * one the port's promise spells -- only the composition names the
 * driver: the `store` knob's `memory` word (the default) composes
 * the middleware over the in-memory driver, and its `file` word
 * composes it over the filesystem adapter ({@see FileSessionAdapter})
 * at the directory the family's wiring answers ({@see
 * SessionStorageWiring}: an explicitly spelled `http.session.path`
 * verbatim, the storage layout's `sessions` lane when a root is
 * declared, and a named halt when the `file` word is spelled with
 * neither -- a silently defaulted directory the host never asked
 * for is exactly the typo the strict census exists to catch).
 *
 * @api
 */
final readonly class SessionFactory
{
    /**
     * Builds the middleware from the frozen repository's
     * `http.session` subtree.
     *
     * @throws ConfigException when the `file` store word is spelled
     *                         without a directory to compose over, or the storage map the
     *                         derivation reads is malformed
     */
    public static function fromConfig(Config $config): SessionMiddleware
    {
        $sessionPolicy = SessionPolicy::fromMap(
            HttpFamilies::subtree($config->get('http'), 'session'),
        );

        if ($sessionPolicy->store !== SessionStores::FILE) {
            return new SessionMiddleware($sessionPolicy);
        }

        $directory = SessionStorageWiring::directory($sessionPolicy->path, $config);

        if ($directory === '') {
            throw new ConfigException(
                'The http.session file store needs a directory: declare storage.root'
                . ' or spell http.session.path.',
            );
        }

        return new SessionMiddleware(
            $sessionPolicy,
            new FileSessionAdapter($directory, $sessionPolicy->idleTimeout),
        );
    }
}
