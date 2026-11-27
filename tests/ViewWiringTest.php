<?php

declare(strict_types=1);

namespace Zef\Test;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\View\PhpViewEngine;
use Zef\Framework\Adapters\View\TwigViewEngine;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\View\ViewEngine;
use Zef\Framework\View\ViewName;
use Zef\Framework\ViewWiring;

/**
 * Pins the view family's wiring: the wired default's composition
 * matrix (the `php` word composing the native engine over the
 * declared templates root, rendering end to end through the port --
 * the silent declaration's named halt with the way out spelled, the
 * address-less `php` word's combination refusal, the root posture
 * flowing through the adapter's own refusal -- two questions, two
 * seams) -- and the composition seam's three boot pins: the service
 * identity resolving over a real boot and rendering, the named halt
 * the silent knob answers at the host's own ask, and the warm boot
 * staying green on the silent knob (the transient registration's
 * own posture -- the warmer never resolves the family).
 *
 * @internal
 */
#[CoversClass(ViewWiring::class)]
final class ViewWiringTest extends TestCase
{
    private string $root;

    private string $bootParent = '';

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-view-wiring-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0o700);

        file_put_contents(
            $this->root . '/greet.php',
            'Hello, <?= $_view->escape($name) ?>!',
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTree($this->root);

        if ($this->bootParent === '') {
            return;
        }

        $this->removeTree($this->bootParent);
    }

    public function testASilentDeclarationRefusesWithTheWayOutSpelled(): void
    {
        try {
            ViewWiring::engine(new Config([
                'views' => ['engine' => ''],
            ]));
            self::fail('Resolving the wired default on a silent declaration must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The views.engine wired default is not declared:'
                . " spell the 'php' word (views.engine) or compose"
                . ' the engine by hand over the ViewEngine port.',
                $configException->getMessage(),
            );
        }
    }

    public function testThePhpWordComposesTheEngineOverTheDeclaredRoot(): void
    {
        $engine = ViewWiring::engine(new Config([
            'views' => ['engine' => 'php', 'path' => $this->root],
        ]));

        self::assertInstanceOf(PhpViewEngine::class, $engine);

        self::assertSame(
            'Hello, Zef &amp; Co!',
            $engine->render(new ViewName('greet'), ['name' => 'Zef & Co']),
            "the composed engine answers the port end to end -- the word wired the lane's whole discipline",
        );
    }

    public function testThePhpWordWithAnEmptyPathRefuses(): void
    {
        try {
            ViewWiring::engine(new Config([
                'views' => ['engine' => 'php'],
            ]));
            self::fail('The php word without a templates root must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The views php engine needs a directory: spell views.path.',
                $configException->getMessage(),
            );
        }
    }

    public function testTheTwigWordComposesTheTwigEngineOverTheDeclaredRoot(): void
    {
        file_put_contents($this->root . '/greet.twig', 'Hello, {{ name }}!');

        $engine = ViewWiring::engine(new Config([
            'views' => ['engine' => 'twig', 'path' => $this->root],
        ]));

        self::assertInstanceOf(TwigViewEngine::class, $engine);

        self::assertSame(
            'Hello, Zef &amp; Co!',
            $engine->render(new ViewName('greet'), ['name' => 'Zef & Co']),
            "the twig word wires the optional answer over the host tree's own Twig end to"
                . ' end -- the same composition discipline the php word spelled, the word'
                . ' answering the engine and the declared root answering WHERE',
        );
    }

    public function testTheTwigWordWithAnEmptyPathRefuses(): void
    {
        try {
            ViewWiring::engine(new Config([
                'views' => ['engine' => 'twig'],
            ]));
            self::fail('The twig word without a templates root must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The views twig engine needs a directory: spell views.path.',
                $configException->getMessage(),
                "the address-less word's combination refusal spells the word it refuses --"
                    . " the php twin's message, word for word with the word swapped",
            );
        }
    }

    public function testARootThatRefusesThePostureFlowsThroughTheAdaptersOwnRefusal(): void
    {
        try {
            ViewWiring::engine(new Config([
                'views' => ['engine' => 'php', 'path' => $this->root . '/a-root-that-was-never-provisioned'],
            ]));
            self::fail("A path that refuses the root posture must flow through the adapter's own refusal.");
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The view templates root must be an existing readable non-symlink directory.',
                $invalidArgumentException->getMessage(),
                "the wiring answers WHERE, never HOW PRIVATE -- the posture is the adapter's own seam",
            );
        }
    }

    public function testTheBootResolvesTheWiredEngineOverTheDeclaredRoot(): void
    {
        $bootTree = $this->temporaryBootTree([
            'views.php' => '<?php return ["engine" => "php", "path" => '
                . var_export($this->root, true) . '];',
        ]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        $engine = $kernel->container()->get('views.engine');

        self::assertInstanceOf(ViewEngine::class, $engine);

        self::assertSame(
            'Hello, Zef!',
            $engine->render(new ViewName('greet'), ['name' => 'Zef']),
            'the booted service identity answers the port end to end',
        );

        self::assertNotSame(
            $engine,
            $kernel->container()->get('views.engine'),
            'the transient registration answers a fresh engine every resolution -- the depth ledger always comes home',
        );
    }

    public function testTheBootResolvesTheWiredTwigEngineOverTheDeclaredRoot(): void
    {
        file_put_contents($this->root . '/greet.twig', 'Hello, {{ name }}!');

        $bootTree = $this->temporaryBootTree([
            'views.php' => '<?php return ["engine" => "twig", "path" => '
                . var_export($this->root, true) . '];',
        ]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        $engine = $kernel->container()->get('views.engine');

        self::assertInstanceOf(ViewEngine::class, $engine);

        self::assertSame(
            'Hello, Zef!',
            $engine->render(new ViewName('greet'), ['name' => 'Zef']),
            'the booted service identity answers the twig word end to end',
        );

        self::assertNotSame(
            $engine,
            $kernel->container()->get('views.engine'),
            'the transient registration answers a fresh twig engine every resolution --'
                . ' the fresh environment per ask rides the same observably-same-engine'
                . " posture the native answer's depth ledger spelled",
        );
    }

    public function testTheBootRefusesTheServiceOnTheSilentKnob(): void
    {
        $bootTree = $this->temporaryBootTree([]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        try {
            $kernel->container()->get('views.engine');
            self::fail('Resolving the wired default without declaring it must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The views.engine wired default is not declared:'
                . " spell the 'php' word (views.engine) or compose"
                . ' the engine by hand over the ViewEngine port.',
                $configException->getMessage(),
            );
        }
    }

    public function testAWarmBootStaysGreenOnTheSilentKnob(): void
    {
        $bootTree = $this->temporaryBootTree([
            'aot.php' => '<?php return ["enabled" => false, "warm" => true];',
            // The default profile arms the CSRF and auth families without
            // secrets or tokens of their own -- a host opting into the
            // warm boot spells them or disarms the families; this pin is
            // about the view family's own posture, so both are disarmed.
            'security.php' => '<?php return ["csrf" => ["enabled" => false],'
                . ' "auth" => ["enabled" => false]];',
        ]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        self::assertTrue(
            $kernel->container()->has('views.engine'),
            'the warm boot answers the registration without ever resolving the family',
        );
    }

    /**
     * Lays a boot environment down under the temporary root: a
     * configuration directory (one file per entry) beside the
     * already-provisioned templates root the declaration names.
     *
     * @param array<string, string> $files the configuration files to write,
     *                                     keyed by basename
     *
     * @return non-empty-string the parent directory holding the pair
     */
    private function temporaryBootTree(array $files): string
    {
        $parent = sys_get_temp_dir() . '/zef-view-wiring-boot-' . bin2hex(random_bytes(8));
        mkdir($parent . '/configs', 0o700, true);

        $this->bootParent = $parent;

        foreach ($files as $name => $content) {
            file_put_contents($parent . '/configs/' . $name, $content);
        }

        return $parent;
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
