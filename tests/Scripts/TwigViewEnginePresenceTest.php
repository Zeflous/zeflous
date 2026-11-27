<?php

declare(strict_types=1);

namespace Zef\Test\Scripts;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The Twig answer's presence boundary, exercised the only way its
 * false arm can be observed: as a real child process that carries
 * the framework's own classes WITHOUT the host tree's autoload --
 * the same zero-vendor posture `bin/zef`'s own fallback loader spells
 * (the framework prefix onto `src/`, the root constant
 * `src/autoload.php` publishes): a child that registers the
 * framework mapping alone answers `class_exists(Twig\Environment)`
 * false, so the constructor's fail-closed probe fires with the
 * remediation spelled out -- the phpredis boundary's unavailable
 * posture, pinned here because a main process that carries the dev
 * tree's own Twig (the require-dev presence the adapter's battery
 * rides) can never observe its absence. The child prints the
 * refusal and exits with its own code so the parent's assertions
 * observe the boundary, never the fatal a class-not-found would
 * answer with.
 *
 * @internal
 */
#[CoversNothing]
final class TwigViewEnginePresenceTest extends TestCase
{
    /**
     * The refusal the boundary answers when the host tree carries no
     * Twig: the remediation spelled out, byte-for-byte the message
     * the adapter's probe raises.
     */
    private const string REFUSAL = "The views twig engine needs Twig on the host tree's own"
        . ' dependency tree: install twig/twig (the framework never carries it) or spell'
        . " the 'php' word (views.engine).";

    private string $root = '';

    #[Override]
    protected function tearDown(): void
    {
        if ($this->root === '') {
            return;
        }

        $entries = scandir($this->root);

        foreach (\is_array($entries) ? $entries : [] as $base) {
            if ($base !== '.' && $base !== '..') {
                unlink($this->root . \DIRECTORY_SEPARATOR . $base);
            }
        }

        rmdir($this->root);

        $this->root = '';
    }

    public function testAHostTreeWithoutTwigRefusesWithTheRemediationSpelledOut(): void
    {
        [$exitCode, $output] = $this->runWithoutTheHostAutoload();

        self::assertSame(3, $exitCode, 'the child answers its own refusal code, never a fatal');
        self::assertSame(self::REFUSAL . "\n", $output, 'the refusal names the remediation');
    }

    /**
     * Runs the adapter's construction in a child process that carries
     * the framework's own classes alone: the framework root as the
     * first argument, the provisioned templates root as the second,
     * and NOTHING of the host tree's own autoload -- the zero-vendor
     * posture the entrypoint itself boots under.
     *
     * @return array{0: int, 1: string} the child's exit code and both streams
     */
    private function runWithoutTheHostAutoload(): array
    {
        $repository = \dirname(__DIR__, 2);

        $this->root = sys_get_temp_dir() . '/zef-twig-presence-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0o700);
        file_put_contents($this->root . '/greet.twig', 'Hello, {{ name }}!');

        $script = sys_get_temp_dir() . '/zef-twig-presence-child-' . bin2hex(random_bytes(8)) . '.php';

        $child = <<<'PHP'
            <?php

            declare(strict_types=1);

            // The zero-vendor posture bin/zef itself boots under: the framework
            // root constant plus the framework mapping alone -- no host autoload,
            // so a class the tree never carries answers class_exists false.
            $framework = $argv[1];

            require $framework . '/src/autoload.php';

            spl_autoload_register(static function (string $class) use ($framework): void {
                $prefix = 'Zef\Framework\\';

                if (!str_starts_with($class, $prefix)) {
                    return;
                }

                require $framework . '/src/Zef/Framework/'
                    . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            });

            try {
                new Zef\Framework\Adapters\View\TwigViewEngine($argv[2]);
            } catch (Zef\Framework\View\ViewException $refusal) {
                fwrite(STDOUT, $refusal->getMessage() . "\n");
                exit(3);
            }

            fwrite(STDOUT, "constructed\n");
            exit(0);
            PHP;

        file_put_contents($script, $child);

        try {
            $process = new Process([\PHP_BINARY, $script, $repository, $this->root], $repository);
            $process->run();

            return [$process->getExitCode() ?? 255, $process->getOutput() . $process->getErrorOutput()];
        } finally {
            unlink($script);
        }
    }
}
