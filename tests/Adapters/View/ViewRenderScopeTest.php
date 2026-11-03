<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\View;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\View\TemplateFace;
use Zef\Framework\Adapters\View\ViewRenderScope;
use Zef\Framework\View\ViewException;
use Zef\Framework\View\ViewName;
use Zef\Test\Support\Adapters\View\RecordingViewEngine;

/**
 * Pins the buffering discipline over real templates: the isolated
 * static scope answers the captured text, a template's own properly
 * balanced inner buffer rides inside the answer, EXTR_SKIP keeps
 * the reserved carriers the corridor's grammar already guards
 * (the belt-and-braces second wall, pinned by handing the scope an
 * impostor bag directly), and a template that closes the engine's
 * buffer, leaves one of its own open, or crashes mid-render meets
 * the typed refusal -- with the caller's own buffer level exactly
 * where it started on every path.
 *
 * @internal
 */
#[CoversClass(ViewRenderScope::class)]
final class ViewRenderScopeTest extends TestCase
{
    private string $directory;

    private TemplateFace $face;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-view-scope-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
        $this->face = new TemplateFace(new RecordingViewEngine());
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTree($this->directory);
    }

    public function testAnswersTheCapturedText(): void
    {
        $file = $this->template('echoer', 'Hello, <?= $_view->escape($who) ?>');

        self::assertSame(
            'Hello, &lt;Zef&gt;',
            ViewRenderScope::render($file, $this->face, ['who' => '<Zef>'], new ViewName('echoer')),
        );
    }

    public function testAnswersAnEmptyStringForASilentTemplate(): void
    {
        $file = $this->template('silent', '<?php return; ?>');

        self::assertSame(
            '',
            ViewRenderScope::render($file, $this->face, [], new ViewName('silent')),
        );
    }

    public function testATemplatesOwnBalancedBufferRidesInsideTheAnswer(): void
    {
        $file = $this->template(
            'balanced',
            '<?php ob_start(); echo \'inner\'; ob_end_flush(); ?>outer',
        );

        self::assertSame(
            'innerouter',
            ViewRenderScope::render($file, $this->face, [], new ViewName('balanced')),
        );
    }

    public function testTheReservedCarriersSurviveAnImpostorBag(): void
    {
        $file = $this->template('impostor', '<?= $payload ?> via <?= $_view->escape(\'<i>\') ?>');

        self::assertSame(
            'p via &lt;i&gt;',
            ViewRenderScope::render(
                $file,
                $this->face,
                ['_view' => 'impostor', '_file' => 'impostor', 'payload' => 'p'],
                new ViewName('impostor'),
            ),
        );
    }

    public function testATemplateThatClosesTheEnginesBufferIsRefused(): void
    {
        $file = $this->template('closer', '<?php ob_end_clean();');

        $entry = ob_get_level();

        try {
            ViewRenderScope::render($file, $this->face, [], new ViewName('closer'));
            self::fail('The buffer-closing template was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                'The view template \'closer\' disturbed the engine\'s output buffering'
                . ' discipline.',
                $viewException->getMessage(),
            );
        } finally {
            self::assertSame($entry, ob_get_level(), 'The caller\'s own buffer level moved.');
        }
    }

    public function testATemplateThatLeavesABufferOpenIsRefused(): void
    {
        $file = $this->template('leaver', '<?php ob_start(); ?>left open');

        $entry = ob_get_level();

        try {
            ViewRenderScope::render($file, $this->face, [], new ViewName('leaver'));
            self::fail('The buffer-leaving template was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                'The view template \'leaver\' disturbed the engine\'s output buffering'
                . ' discipline.',
                $viewException->getMessage(),
            );
        } finally {
            self::assertSame($entry, ob_get_level(), 'The caller\'s own buffer level moved.');
        }
    }

    public function testATemplateThatCrashesPropagatesWithEveryBufferPopped(): void
    {
        $file = $this->template('crasher', '<?php throw new RuntimeException(\'scope-boom\'); ?>');

        $entry = ob_get_level();

        try {
            ViewRenderScope::render($file, $this->face, [], new ViewName('crasher'));
            self::fail('The crashing template did not propagate its own throwable.');
        } catch (\RuntimeException $runtimeException) {
            self::assertSame('scope-boom', $runtimeException->getMessage());
        } finally {
            self::assertSame($entry, ob_get_level(), 'A crashed render left a buffer behind.');
        }
    }

    private function template(string $name, string $source): string
    {
        $file = $this->directory . '/' . $name . '.php';
        file_put_contents($file, $source);

        return $file;
    }

    private function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        foreach (\is_array($entries) ? $entries : [] as $base) {
            if ($base === '.' || $base === '..') {
                continue;
            }

            unlink($directory . \DIRECTORY_SEPARATOR . $base);
        }

        rmdir($directory);
    }
}
