<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleInterface;

/**
 * The two-phase boot's carry between its phases: the ordered module set the
 * registration phase built, ready to run its boot phase when the
 * container's own definitions have settled.
 *
 * The roster is deliberately the only state the module boot keeps across
 * the phases -- discovery, ordering and registration answer before the
 * container bootstrapper runs; the boot phase runs after it, walking the
 * same order and handing each module its own validated
 * {@see ModuleContext}. A roster boots once: a second `boot()` call is a
 * refusal, because a phase that ran twice is a boot that no longer knows
 * which side of itself each module saw.
 */
final class ModuleRoster
{
    /** @var list<array{declaration: ModuleDeclaration, module: ModuleInterface}> */
    private array $entries = [];

    private bool $booted = false;

    public function __construct(
        private readonly Config $config,
        private readonly ModuleContextFactory $moduleContextFactory = new ModuleContextFactory(),
    ) {
    }

    /**
     * Files one module onto the roster, in the boot order the ordering fold
     * answered. The registration phase's own seam -- the bootstrapper is
     * the caller.
     */
    public function add(ModuleDeclaration $moduleDeclaration, ModuleInterface $module): void
    {
        $this->entries[] = ['declaration' => $moduleDeclaration, 'module' => $module];
    }

    /**
     * Runs the boot phase: every module receives its own readonly context,
     * in the roster's order.
     *
     * @throws ModuleException when the roster already booted, or a module's
     *                         configuration block fails its own schema
     */
    public function boot(): void
    {
        if ($this->booted) {
            throw new ModuleException(
                'The module roster has already booted; a roster boots once '
                . 'per process.',
            );
        }

        $this->booted = true;

        foreach ($this->entries as $entry) {
            $entry['module']->boot(
                $this->moduleContextFactory->context($entry['declaration'], $this->config),
            );
        }
    }
}
