<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Database\MigrationException;
use Zef\Framework\Database\MigrationFiles;
use Zef\Framework\Database\Migrator;

/**
 * The migrations commands' boot: the one seam `bin/zef
 * migrations:status`, `migrations:run` and `migrations:rollback`
 * share -- a configuration directory becomes the REGISTERED runner
 * those commands ask their questions of, booted through the same
 * application orchestrator the runtime rides ({@see
 * ApplicationBootstrapper}, the analysis CLIs' own doctrine: never a
 * parallel parse), the runner answered from the container's own
 * `database.migrator` service identity the framework profile
 * registers -- the wiring slice's per-purpose connection, the
 * migrations name the host spelled, else the default.
 *
 * The operator's migrations directory joins through the discovery
 * fold ({@see MigrationFiles}): one `*.php` file, one instance, the
 * file's own return the declaration, every version registered with
 * the runner BEFORE the first question is asked -- so the verbs
 * answer over the whole declared set, never a partial registration,
 * and the discovery's own refusals (the missing directory, the stray
 * return, the version two files both declare) fire at the boot seam
 * where the CLI's guarded exit doctrine catches them.
 *
 * The first `register` call is the host's first use of the lazy
 * runner: the silent `database` profile (the absent knob block)
 * refuses exactly there -- never at the boot itself, the
 * never-at-the-boot discipline the wiring slice planted -- which is
 * the CLI's honest posture too: a deployment that never spelled the
 * connection learns it from the command that needed it, not from a
 * boot that would have invented one.
 *
 * The container AOT artifact rides the throwaway file the analysis
 * CLIs share ({@see ContainerAnalysisArtifact}) and never outlives
 * the boot.
 */
final readonly class MigrationsBoot
{
    /**
     * @param array<string, string> $names the declared migrations' names
     *                                     keyed by version stamp
     */
    private function __construct(
        private Migrator $migrator,
        /**
         * The declared migrations' names keyed by version stamp -- the
         * presentation units' labeling source, the honest `(undeclared)`
         * spelling for an applied version whose declaration has since
         * left the directory.
         */
        private array $names
    ) {
    }

    /**
     * Boots the configuration directory's runner with the migrations
     * directory's declarations registered.
     *
     * @param non-empty-string $configDirectory     the host's configuration
     *                                              directory (the `app/configs` layout)
     * @param non-empty-string $migrationsDirectory the operator's migrations
     *                                              directory (one `*.php` file per
     *                                              declared migration)
     *
     * @throws ConfigException    when the silent database
     *                            profile refuses at the host's first use
     * @throws MigrationException when the migrations directory
     *                            or a declaration refuses
     */
    public static function booted(string $configDirectory, string $migrationsDirectory): self
    {
        $artifactFile = ContainerAnalysisArtifact::path();

        try {
            $kernel = new ApplicationBootstrapper(
                containerAotCache: new ContainerAotCache($artifactFile),
            )->boot($configDirectory);

            $migrator = $kernel->container()->get('database.migrator');
            \assert($migrator instanceof Migrator);

            $names = [];

            foreach (MigrationFiles::discover($migrationsDirectory) as $version => $migration) {
                $migrator->register($migration);
                $names[$version] = $migration->name();
            }

            return new self($migrator, $names);
        } finally {
            if (is_file($artifactFile)) {
                unlink($artifactFile);
            }
        }
    }

    /**
     * The declared migrations' names keyed by version stamp.
     *
     * @return array<string, string>
     */
    public function names(): array
    {
        return $this->names;
    }

    /**
     * The versions recorded as applied, ascending.
     *
     * @return list<string>
     */
    public function applied(): array
    {
        return $this->migrator->applied();
    }

    /**
     * The versions migrate() would apply right now.
     *
     * @return list<string>
     */
    public function plan(): array
    {
        return $this->migrator->plan();
    }

    /**
     * Applies every pending migration.
     *
     * @return list<string> the versions applied now
     */
    public function migrate(): array
    {
        return $this->migrator->migrate();
    }

    /**
     * Rolls back the $steps most recent applied migrations.
     *
     * @return list<string> the versions rolled back
     */
    public function rollback(int $steps): array
    {
        return $this->migrator->rollback($steps);
    }
}
