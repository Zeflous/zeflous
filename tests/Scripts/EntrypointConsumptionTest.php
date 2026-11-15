<?php

declare(strict_types=1);

namespace Zef\Test\Scripts;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The CLI entrypoint's dependency-consumption posture, exercised end to
 * end as real subprocesses over synthetic consumption trees the way the
 * advertised modes invoke them (issue #775): the entrypoint's own
 * autoloading seam -- the one place every verb's classes load through --
 * must answer the consumption shapes the manifest advertises, not only
 * the framework's own dev checkout.
 *
 * The battery's consumption trees spell the real installs' own shapes.
 * The `--no-dev` checkout: the consuming project's generated autoloader
 * maps the production prefixes alone (`autoload-dev` never runs for a
 * dependency consumption), the very arm the audit found fataling with a
 * Class-not-found while the entrypoint's vendor-present arm returned
 * after Composer's mapping alone. The vendored package: the package
 * under `vendor/zeflous/zeflous` with the consumer's own autoloader two
 * levels above the package's bin, where the entrypoint's one-level-up
 * lookup never finds it -- the hand-spelled fallback must answer alone.
 * The narrowed distribution: no tooling tree at all, the install shape
 * the honest refusal names instead of the fatal. The manifest's own `bin`
 * registration is pinned beside them: the entrypoint the consuming
 * project's `vendor/bin/` proxies, the surface without which the
 * dependency consumption has no verb to invoke at all.
 *
 * The trees are throwaway directories under the system temporary
 * directory, staged entry by entry and torn down by the same ledger, so
 * a tree never outlives its test and the symlinked repository halves are
 * never followed into -- exactly the files a dist install carries.
 *
 * @internal
 */
#[CoversNothing]
final class EntrypointConsumptionTest extends TestCase
{
    private const string FIXTURE = 'tests/Scripts/fixtures/configs';

    /**
     * The staged trees' file ledger: every copied file and every symlink
     * by its absolute path, so teardown unlinks the link itself and never
     * the repository tree it points into.
     *
     * @var list<string>
     */
    private array $files = [];

    /**
     * The staged trees' directory ledger in creation order, so teardown
     * removes them child-first.
     *
     * @var list<string>
     */
    private array $directories = [];

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file) || is_link($file)) {
                unlink($file);
            }
        }

        foreach (array_reverse($this->directories) as $directory) {
            if (is_dir($directory) && !is_link($directory)) {
                rmdir($directory);
            }
        }

        $this->files = [];
        $this->directories = [];
    }

    /**
     * The audit's own reproduction shape: a checkout whose generated
     * autoloader maps the production prefixes alone -- the `--no-dev`
     * install the manifest's consumption mode advertises. The verb
     * answers over the tooling fallback the entrypoint registers beside
     * Composer's mapping, the load the early return once refused to
     * spell.
     */
    public function testANoDevInstallAnswersItsVerbOverTheToolingFallback(): void
    {
        $tree = $this->checkoutTree(true);

        [$exitCode, $output] = $this->runCommand($tree . '/bin/zef', ['list', self::FIXTURE]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Zef commands --', $output);
        self::assertStringContainsString('route:list', $output);
    }

    /**
     * The vendored package: the consumer's own autoloader sits two levels
     * above the package's bin, where the entrypoint never finds it -- the
     * hand-spelled fallback answers alone, the load the bare-install arm
     * has always owned.
     */
    public function testAVendoredInstallAnswersItsVerbOverTheHandSpelledFallback(): void
    {
        $tree = $this->vendoredTree();

        [$exitCode, $output] = $this->runCommand(
            $tree . '/vendor/zeflous/zeflous/bin/zef',
            ['list', self::FIXTURE],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Zef commands --', $output);
        self::assertStringContainsString('route:list', $output);
    }

    /**
     * The narrowed distribution: an install without the tooling tree is
     * the operational refusal (exit 1, the vocabulary CommandHandler
     * itself spells), the words naming the missing tree and the way out
     * -- never the fatal the Class-not-found once answered with.
     */
    public function testAnInstallWithoutTheToolingTreeAnswersTheOperationalRefusalNamingIt(): void
    {
        $tree = $this->checkoutTree(false);

        [$exitCode, $output] = $this->runCommand($tree . '/bin/zef', ['list', self::FIXTURE]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('cannot answer any bin/zef verb', $output);
        self::assertStringContainsString('tests/Support/Tooling/', $output);
    }

    /**
     * The manifest's own registration: the entrypoint the consuming
     * project's `vendor/bin/` proxies -- the surface without which the
     * dependency consumption has no verb to invoke at all.
     */
    public function testTheManifestPublishesTheEntrypointBinary(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(\dirname(__DIR__, 2) . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($manifest);
        self::assertSame(['bin/zef'], $manifest['bin'] ?? null);
    }

    /**
     * Stages the `--no-dev` checkout shape: the entrypoint beside the
     * runtime tree and (when asked) the tooling tree, the consuming
     * project's generated autoloader mapping the production prefixes
     * alone.
     *
     * @param bool $withTooling whether the tree carries the tooling
     *                          support classes the verbs ride
     *
     * @return non-empty-string the checkout root
     */
    private function checkoutTree(bool $withTooling): string
    {
        $root = \dirname(__DIR__, 2);
        $tree = sys_get_temp_dir() . '/zef-consumption-' . uniqid('', false);

        $this->directories[] = $tree;
        $this->directories[] = $tree . '/bin';
        mkdir($tree . '/bin', 0o777, true);

        $this->files[] = $tree . '/bin/zef';
        copy($root . '/bin/zef', $tree . '/bin/zef');

        $this->files[] = $tree . '/src';
        symlink($root . '/src', $tree . '/src');

        if ($withTooling) {
            $this->files[] = $tree . '/tests';
            symlink($root . '/tests', $tree . '/tests');
        }

        $this->directories[] = $tree . '/vendor';
        mkdir($tree . '/vendor');

        $this->files[] = $tree . '/vendor/autoload.php';
        file_put_contents(
            $tree . '/vendor/autoload.php',
            $this->productionAutoloader($tree),
        );

        return $tree;
    }

    /**
     * Stages the vendored package shape: the package under
     * `vendor/zeflous/zeflous` with its entrypoint, runtime tree and
     * tooling tree, the consuming project's own autoloader at the vendor
     * root -- two levels above the package's bin, where the entrypoint's
     * one-level-up lookup never reaches it.
     *
     * @return non-empty-string the project root the package sits in
     */
    private function vendoredTree(): string
    {
        $root = \dirname(__DIR__, 2);
        $tree = sys_get_temp_dir() . '/zef-vendored-' . uniqid('', false);
        $package = $tree . '/vendor/zeflous/zeflous';

        $this->directories[] = $tree;
        $this->directories[] = $tree . '/vendor';
        $this->directories[] = $tree . '/vendor/zeflous';
        $this->directories[] = $package;
        $this->directories[] = $package . '/bin';
        mkdir($package . '/bin', 0o777, true);

        $this->files[] = $package . '/bin/zef';
        copy($root . '/bin/zef', $package . '/bin/zef');

        $this->files[] = $package . '/src';
        symlink($root . '/src', $package . '/src');

        $this->files[] = $package . '/tests';
        symlink($root . '/tests', $package . '/tests');

        $this->files[] = $tree . '/vendor/autoload.php';
        file_put_contents(
            $tree . '/vendor/autoload.php',
            $this->productionAutoloader($package),
        );

        return $tree;
    }

    /**
     * The consuming project's generated autoloader, spelled the way
     * Composer generates it for a dependency consumption: the production
     * prefixes the manifest's own `autoload` block maps, pointed at the
     * installed package's tree, plus the files-include bootstrap the
     * block also publishes -- and nothing else, exactly the mapping
     * shape the audit measured (`autoload-dev` never runs for an
     * install that consumes the package as a dependency).
     *
     * @param non-empty-string $packageBase the installed package's root
     *
     * @return non-empty-string the generated file's whole content
     */
    private function productionAutoloader(string $packageBase): string
    {
        $bases = [
            'Zef\Framework\\' => $packageBase . '/src/Zef/Framework/',
            'Psr\Container\\' => $packageBase . '/src/Contracts/Psr/Container/',
            'Psr\Http\Message\\' => $packageBase . '/src/Contracts/Psr/Http/Message/',
            'Psr\Http\Server\\' => $packageBase . '/src/Contracts/Psr/Http/Server/',
            'Psr\Log\\' => $packageBase . '/src/Contracts/Psr/Log/',
        ];

        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            '$bases = ' . var_export($bases, true) . ';',
            'spl_autoload_register(static function (string $class) use ($bases): void {',
            '    foreach ($bases as $prefix => $base) {',
            '        if (!str_starts_with($class, $prefix)) {',
            '            continue;',
            '        }',
            '',
            '        $file = $base . str_replace(\'\\\\\', \'/\','
            . ' mb_substr($class, mb_strlen($prefix))) . \'.php\';',
            '        if (!is_file($file)) {',
            '            continue;',
            '        }',
            '',
            '        require $file;',
            '',
            '        return;',
            '    }',
            '});',
            '',
            'require_once ' . var_export($packageBase . '/src/autoload.php', true) . ';',
        ];

        return implode("\n", $lines) . "\n";
    }

    /**
     * Runs the entrypoint as a real subprocess the way a host's shell
     * invokes it, from the framework checkout's root.
     *
     * @param list<string> $arguments the verb and its own arguments
     *
     * @return array{0: int, 1: string} the exit code and the combined output
     */
    private function runCommand(string $entrypoint, array $arguments): array
    {
        $process = new Process(
            [\PHP_BINARY, $entrypoint, ...$arguments],
            \dirname(__DIR__, 2),
        );
        $process->run();

        return [$process->getExitCode() ?? 255, $process->getOutput() . $process->getErrorOutput()];
    }
}
