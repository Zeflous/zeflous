<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\View;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Zef\Framework\Adapters\View\TwigViewEngine;
use Zef\Framework\View\ViewException;
use Zef\Framework\View\ViewName;

/**
 * Pins the presentation port's Twig answer: the optional adapter over
 * the host tree's own Twig (the presence probed fail-closed at the
 * constructor -- the false arm's own battery lives in the subprocess
 * pin, {@see \Zef\Test\Scripts\TwigViewEnginePresenceTest}, because a
 * process that carries the dev tree's own Twig cannot observe its
 * absence), the shared root posture flowing through the adapter's own
 * construction, the escaping doctrine pinned explicitly on the
 * environment, the extension-free name resolving through the answer's
 * own `.twig` extension, the data bag riding the Twig context verbatim
 * (the native corridor's reserved names are the native scope's own),
 * and the wrap discipline -- the lane's own miss vocabulary translated
 * with the cause chained, every other foreign throwable wrapped with
 * the template named and the cause chained (the native twin's
 * mid-render message, byte-identical).
 *
 * @internal
 */
#[CoversClass(TwigViewEngine::class)]
final class TwigViewEngineTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-twig-engine-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testRendersATemplateWithTheEscapingDoctrinePinned(): void
    {
        file_put_contents($this->root . '/greet.twig', 'Hello, {{ name }}!');

        $twigViewEngine = new TwigViewEngine($this->root);

        self::assertSame(
            'Hello, Zef &amp; Co!',
            $twigViewEngine->render(new ViewName('greet'), ['name' => 'Zef & Co']),
            "Twig's own auto-escape-on-output satisfies the lane's doctrine natively --"
            . ' the environment composes with the strategy pinned explicitly',
        );
    }

    public function testTheExtensionFreeNameResolvesThroughTheAnswersOwnExtension(): void
    {
        mkdir($this->root . '/users');
        file_put_contents($this->root . '/users/show.twig', '{{ user.name }}');

        $twigViewEngine = new TwigViewEngine($this->root);

        self::assertSame(
            'A &amp; B',
            $twigViewEngine->render(new ViewName('users/show'), ['user' => ['name' => 'A & B']]),
            'the identity that survives the port boundary is the extension-free name --'
            . ' the answer joins its own .twig extension and the loader resolves it',
        );
    }

    public function testTheDataBagRidesTheTwigContextVerbatim(): void
    {
        file_put_contents($this->root . '/bag.twig', 'this={{ this }}');

        $twigViewEngine = new TwigViewEngine($this->root);

        self::assertSame(
            'this=carried',
            $twigViewEngine->render(new ViewName('bag'), ['this' => 'carried']),
            'the native corridor\'s reserved names are the native scope\'s own ($_view and'
            . ' the extraction carriers) -- the Twig context carries the bag verbatim,'
            . " exactly as the native fold's docblock recorded it would",
        );
    }

    public function testAMissIsTranslatedIntoTheLanesOwnMissRefusalWithTheCauseChained(): void
    {
        file_put_contents($this->root . '/present.twig', 'present');

        $twigViewEngine = new TwigViewEngine($this->root);

        try {
            $twigViewEngine->render(new ViewName('absent'), []);
            self::fail('A miss below the templates root must refuse.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "The view template 'absent' does not answer under the templates root.",
                $viewException->getMessage(),
                "the lane's miss vocabulary stays uniform across answers -- the template"
                . " named by the caller's own extension-free spelling, never the loader's"
                . ' path-bearing message as the refusal own words',
            );

            self::assertSame(0, $viewException->getCode());

            self::assertInstanceOf(
                LoaderError::class,
                $viewException->getPrevious(),
                'the translated foreign refusal carries its cause chained -- the loader'
                . " tells the operator which root it looked into, the refusal's own words"
                . ' never quote it',
            );
        }
    }

    public function testASyntaxErrorIsWrappedWithTheTemplateNamedAndTheCauseChained(): void
    {
        file_put_contents($this->root . '/broken.twig', '{% bad %}');

        $twigViewEngine = new TwigViewEngine($this->root);

        try {
            $twigViewEngine->render(new ViewName('broken'), []);
            self::fail('A syntax error must be wrapped into the family.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "The view template 'broken' failed mid-render.",
                $viewException->getMessage(),
                "the native twin's mid-render message, byte-identical -- bad template"
                . " authoring is the developer's defect, the lane's own words name it",
            );

            self::assertSame(0, $viewException->getCode());

            self::assertInstanceOf(
                SyntaxError::class,
                $viewException->getPrevious(),
                'the foreign syntax error travels as the cause, never as the answer',
            );
        }
    }

    public function testARuntimeFailureIsWrappedWithTheTemplateNamedAndTheCauseChained(): void
    {
        file_put_contents($this->root . '/boom.twig', '{{ 5 // zero }}');

        $twigViewEngine = new TwigViewEngine($this->root);

        try {
            $twigViewEngine->render(new ViewName('boom'), ['zero' => 0]);
            self::fail('A foreign runtime failure must be wrapped into the family.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "The view template 'boom' failed mid-render.",
                $viewException->getMessage(),
            );

            self::assertSame(0, $viewException->getCode());

            self::assertInstanceOf(
                RuntimeError::class,
                $viewException->getPrevious(),
                "Twig's own wrap hands the engine failure over -- the lane chains it"
                . ' verbatim, the same cause-chained discipline the native twin spells',
            );
        }
    }

    public function testAnUnprovisionedRootRefusesTheSharedRootPosture(): void
    {
        try {
            new TwigViewEngine($this->root . '/a-root-that-was-never-provisioned');
            self::fail('An unprovisioned root must refuse the shared root posture.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The view templates root must be an existing readable non-symlink directory.',
                $invalidArgumentException->getMessage(),
                "the shared root fold flows through the adapter's own construction"
                . ' untouched -- the wiring answers WHERE, never HOW PRIVATE,'
                . ' two questions two seams',
            );
        }
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

            $entry = $directory . \DIRECTORY_SEPARATOR . $base;

            if (is_dir($entry)) {
                $this->removeTree($entry);
            } else {
                unlink($entry);
            }
        }

        rmdir($directory);
    }
}
