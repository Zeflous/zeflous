<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\View;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Zef\Framework\Adapters\View\PhpViewEngine;
use Zef\Framework\View\ViewException;
use Zef\Framework\View\ViewName;

/**
 * Pins the native engine's whole composition over a real templates
 * tree: the answered render (data extracted, values escaped through
 * the face, raw values under the author's own discipline), the
 * layout-over-partials recursion, the values nobody's corridor
 * polices, and every refusal story -- the miss that names the name
 * alone, the data key corridor through the engine, the mid-render
 * crash wrapped with the template named and the cause chained, the
 * family's own refusals travelling verbatim (a partial's miss keeps
 * the partial's own words), the nesting ceiling a self-recursive or
 * cyclic composition meets, and the ledger left where it was found
 * after every refusal -- plus the root posture the composition
 * itself refuses.
 *
 * @internal
 */
#[CoversClass(PhpViewEngine::class)]
final class PhpViewEngineTest extends TestCase
{
    private string $root;

    private PhpViewEngine $engine;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-view-engine-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0o700);

        $this->template('greet', 'Hello, <?= $_view->escape($name) ?>!');
        $this->template('raw', 'Raw: <?= $title ?>');
        $this->template('layout', 'Layout(<?= $_view->render(\'partial\', [\'item\' => $item]) ?>)');
        $this->template('partial', '[<?= $_view->escape($item) ?>]');
        $this->template('layout-miss', '<?= $_view->render(\'nope\', []) ?>');
        $this->template('crash', '<?php throw new RuntimeException(\'boom\'); ?>');
        $this->template('recurse', '<?= $_view->render(\'recurse\', []) ?>');
        $this->template('cycle-a', 'A<?= $_view->render(\'cycle-b\', []) ?>');
        $this->template('cycle-b', 'B<?= $_view->render(\'cycle-a\', []) ?>');
        $this->template('typed', '<?= get_debug_type($value) ?>');

        $this->engine = new PhpViewEngine($this->root);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testRendersATemplateAgainstItsData(): void
    {
        self::assertSame(
            'Hello, Zef &amp; Co!',
            $this->engine->render(new ViewName('greet'), ['name' => 'Zef & Co']),
        );
    }

    public function testRepeatedRendersAnswerIndependently(): void
    {
        $name = new ViewName('greet');

        self::assertSame(
            'Hello, Zef!',
            $this->engine->render($name, ['name' => 'Zef']),
        );
        self::assertSame(
            'Hello, Zef!',
            $this->engine->render($name, ['name' => 'Zef']),
        );
    }

    public function testRendersRawValuesUnderTheAuthorsOwnDiscipline(): void
    {
        self::assertSame(
            'Raw: <b>',
            $this->engine->render(new ViewName('raw'), ['title' => '<b>']),
        );
    }

    public function testTheValuesAreNobodysCorridor(): void
    {
        $name = new ViewName('typed');

        self::assertSame('stdClass', $this->engine->render($name, ['value' => new stdClass()]));
        self::assertSame('null', $this->engine->render($name, ['value' => null]));
        self::assertSame('array', $this->engine->render($name, ['value' => [1, 2]]));
        self::assertSame(
            'Closure',
            $this->engine->render($name, ['value' => static fn (): string => 'x']),
        );
    }

    public function testComposesALayoutOverItsPartials(): void
    {
        self::assertSame(
            'Layout([x&lt;y])',
            $this->engine->render(new ViewName('layout'), ['item' => 'x<y']),
        );
    }

    public function testAMissNamesTheNameAlone(): void
    {
        try {
            $this->engine->render(new ViewName('missing'), []);
            self::fail('The miss was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                'The view template \'missing\' does not answer under the templates root.',
                $viewException->getMessage(),
            );
            self::assertStringNotContainsString(
                $this->root,
                $viewException->getMessage(),
                'A miss must never disclose the resolved address.',
            );
        }
    }

    public function testTheDataKeyCorridorTravelsTheTypedFamily(): void
    {
        try {
            $this->engine->render(new ViewName('greet'), ['0bad' => 'digit-led']);
            self::fail('The grammar-refusing key was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "A view data key must be a letter-led variable spelling, got '0bad'.",
                $viewException->getMessage(),
            );
        }
    }

    public function testANonStringKeyIsRefusedWithItsOwnType(): void
    {
        /** @var array<string, mixed> $data the int key the runtime bag can hand */
        $data = [42 => 'an integer key'];

        try {
            $this->engine->render(new ViewName('greet'), $data);
            self::fail('The non-string key was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                'A view data key must be a string, got int.',
                $viewException->getMessage(),
            );
        }
    }

    public function testAMidRenderCrashIsWrappedWithTheTemplateNamedAndTheCauseChained(): void
    {
        try {
            $this->engine->render(new ViewName('crash'), []);
            self::fail('The crashing template was not wrapped.');
        } catch (ViewException $viewException) {
            self::assertSame(
                'The view template \'crash\' failed mid-render.',
                $viewException->getMessage(),
            );
            self::assertInstanceOf(RuntimeException::class, $viewException->getPrevious());
            self::assertSame('boom', $viewException->getPrevious()?->getMessage());
        }
    }

    public function testTheFamilysOwnRefusalsTravelVerbatim(): void
    {
        try {
            $this->engine->render(new ViewName('layout-miss'), []);
            self::fail('The partial\'s miss was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                'The view template \'nope\' does not answer under the templates root.',
                $viewException->getMessage(),
            );
        }
    }

    public function testTheNestingCeilingNamesTheRefusedDepth(): void
    {
        $entry = ob_get_level();

        try {
            $this->engine->render(new ViewName('recurse'), []);
            self::fail('The self-recursive template was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                'View rendering nesting of 64 exceeded while rendering \'recurse\'.',
                $viewException->getMessage(),
            );
        } finally {
            self::assertSame($entry, ob_get_level(), 'A refused nesting left a buffer behind.');
        }
    }

    public function testTwoPartialsThatSpellACycleMeetTheSameCeiling(): void
    {
        try {
            $this->engine->render(new ViewName('cycle-a'), []);
            self::fail('The cyclic composition was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                'View rendering nesting of 64 exceeded while rendering \'cycle-a\'.',
                $viewException->getMessage(),
            );
        }
    }

    public function testTheLedgerIsLeftWhereItWasFoundAfterARefusal(): void
    {
        try {
            $this->engine->render(new ViewName('crash'), []);
            self::fail('The crashing template was not refused.');
        } catch (ViewException) {
            // The wrap under test -- the ledger question follows.
        }

        self::assertSame(
            'Hello, Zef!',
            $this->engine->render(new ViewName('greet'), ['name' => 'Zef']),
        );
    }

    public function testARootThatRefusesThePostureRefusesTheComposition(): void
    {
        try {
            new PhpViewEngine($this->root . '-missing');
            self::fail('The missing root was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The view templates root must be an existing readable'
                . ' non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    private function template(string $name, string $source): void
    {
        file_put_contents($this->root . '/' . $name . '.php', $source);
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
