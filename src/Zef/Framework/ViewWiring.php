<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Adapters\View\PhpViewEngine;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\View\ViewEngine;

/**
 * The view family's wiring: the composition fold that answers the
 * presentation port's wired engine from the frozen configuration --
 * the `views` configuration family the unwired lane's own decision
 * record named as its next slice (KDD-VW's named follow-ups, the
 * Database lane's own port-then-wiring order answered), closed by
 * the pull request that lands this class.
 *
 * The question the fold owns is the engine the knob's word composes,
 * spelled at the profile's own service identity (`views.engine`, the
 * registration {@see FrameworkDefaults} declares over this fold):
 * the {@see ViewDeclaration} census reads what the host declared,
 * and the word answers the engine -- the `php` word composing
 * {@see PhpViewEngine} over the declared templates root, the
 * zero-dependency answer the port already carries.
 *
 * The templates root is the deployment's own EXPLICIT address and
 * the fold derives nothing: the `files/**` route map owns no
 * `views` lane because templates are the application's own source
 * the engine only ever answers includes from -- the read-only root
 * doctrine the port's own root fold spells -- never state the
 * framework writes, and the lanes the map owns are the written-state
 * tree. So the `php` word with an empty path is the named halt the
 * session family's combination refusal spelled first ({@see
 * Middleware\SessionFactory}): a directory the host never spelled
 * is never silently defaulted, and the honest way out is the knob's
 * own grammar -- spell `views.path`. A path that refuses the root
 * POSTURE flows through the adapter's own refusal untouched: this
 * fold answers WHERE, never HOW PRIVATE -- two questions, two seams
 * (the events family's doctrine, {@see EventStorageWiring}).
 *
 * The silent declaration (the empty engine word) is a refusal with
 * the way out spelled, never a null answer: a host resolving the
 * wired default's service identity is asking for the framework's
 * engine, and the honest answer to an undeclared ask is the knob's
 * own grammar -- spell the word, or compose the engine by hand over
 * the port ({@see ViewEngine}) the way every composition before this
 * fold did.
 *
 * @api
 */
final readonly class ViewWiring
{
    /**
     * Answers the wired engine from the frozen repository: the
     * `php` word's native answer over the templates root the knob
     * spells.
     *
     * @throws ConfigException when the declaration is silent or
     *                         malformed, or the `php` word is spelled without a
     *                         templates root to compose over
     */
    public static function engine(Config $config): ViewEngine
    {
        $viewDeclaration = ViewDeclaration::fromConfig($config);

        if (!$viewDeclaration->wantsWiredEngine()) {
            throw new ConfigException(
                'The views.engine wired default is not declared:'
                . " spell the 'php' word (views.engine) or compose"
                . ' the engine by hand over the ViewEngine port.',
            );
        }

        if ($viewDeclaration->path === '') {
            throw new ConfigException(
                'The views php engine needs a directory: spell views.path.',
            );
        }

        return new PhpViewEngine($viewDeclaration->path);
    }
}
