<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Cache\ConfigCache;

/**
 * The application boot's cost reference -- the once-per-worker price the
 * one-call orchestrator pays (ROADMAP.md's boot pipeline: load, merge,
 * validate, then the container install), pinned in the CI bench lane.
 *
 * Not a victory claim. The honest cost structure these numbers pin: the
 * cold boot scans the directory, executes every configuration file, merges
 * the layers, validates the route table and installs the service map --
 * a cost that grows with the directory; the cached boot replaces all of
 * that with one artifact `require` plus the same validation and container
 * install (KDD-6's fast path, what a persistent worker takes once per
 * process). On this three-file fixture the two subjects land in the same
 * band (the artifact is the whole merged tree, so a small profile gains
 * nothing) -- the cached subject's value is that its cost stays flat as
 * the profile grows, which is exactly what the artifact exists for.
 */
final readonly class AppBootBench
{
    private const string FIXTURE_CONFIGS = __DIR__ . '/../tests/Kernel/fixtures/configs';

    private string $cacheFile;

    public function __construct()
    {
        $this->cacheFile = sys_get_temp_dir() . '/zef-appboot-bench-' . uniqid('', false) . '.php';
        new ApplicationBootstrapper(new ConfigCache($this->cacheFile))->boot(self::FIXTURE_CONFIGS);
    }

    public function __destruct()
    {
        if (!is_file($this->cacheFile)) {
            return;
        }

        unlink($this->cacheFile);
    }

    #[Revs(100)]
    public function benchColdBootFromConfigurationFiles(): void
    {
        new ApplicationBootstrapper()->boot(self::FIXTURE_CONFIGS);
    }

    #[Revs(100)]
    public function benchCachedBootFromTheCompiledArtifact(): void
    {
        new ApplicationBootstrapper(new ConfigCache($this->cacheFile))->boot(self::FIXTURE_CONFIGS);
    }
}
