<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use FilesystemIterator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Zef\Test\Support\Container\Aot\CallsHouse;
use Zef\Test\Support\Container\Aot\House;
use Zef\Test\Support\Container\Aot\Roof;
use Zef\Test\Support\Container\Aot\Window;
use Zef\Test\Support\Container\Lazy\EngineFactory;
use Zef\Test\Support\Container\Lazy\EngineFuel;
use Zef\Test\Support\Tooling\ContainerAnalysisArtifact;
use Zef\Test\Support\Tooling\ContainerInventoryDetail;
use Zef\Test\Support\Tooling\ContainerInventoryLine;
use Zef\Test\Support\Tooling\ContainerInventoryReport;
use Zef\Test\Support\Tooling\ContainerInventoryText;
use Zef\Test\Support\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(ContainerInventoryReport::class)]
#[CoversClass(ContainerInventoryText::class)]
#[CoversClass(ContainerInventoryLine::class)]
#[CoversClass(ContainerInventoryDetail::class)]
#[CoversClass(ContainerAnalysisArtifact::class)]
final class ContainerInventoryReportTest extends TestCase
{
    /** @var non-empty-string */
    private string $configDirectory;

    #[Override]
    protected function setUp(): void
    {
        $this->configDirectory = sys_get_temp_dir() . '/zef-inventory-report-' . uniqid('', false);

        mkdir($this->configDirectory, 0o777, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->configDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            $path = $file->getPathname();

            $file->isDir() ? rmdir($path) : unlink($path);
        }

        rmdir($this->configDirectory);
    }

    public function testTheTableAnswersEveryDeclaredService(): void
    {
        $this->declare([
            'app.window' => ['class' => Window::class],
            'app.house' => [
                'class' => House::class,
                'arguments' => [['service' => 'app.window'], 4, ['style' => 'georgian']],
                'tags' => ['frame.house'],
            ],
        ]);

        $report = ContainerInventoryReport::rendering($this->configDirectory);

        // The analysis boots the real profile -- the framework's own
        // registrations ride under the host's declarations -- so the
        // counts and the line order are the boot's, not the file's.
        // The pinned shapes: the table opens with its own header (no
        // leading blank line), closes with the final newline, and every
        // declared row carries its two-space indent.
        self::assertStringStartsWith('Container inventory -- ', $report);
        self::assertStringEndsWith("\n", $report);
        self::assertStringContainsString('boot: the aot knob is off', $report);
        self::assertStringContainsString("\n  app.window  class  singleton  " . Window::class, $report);
        self::assertStringContainsString(
            "\n  app.house  class  singleton  " . House::class . '  tags: frame.house  deps: app.window',
            $report,
        );
    }

    public function testTheEntryAnswersEveryField(): void
    {
        $this->declare([
            'app.calls' => [
                'class' => CallsHouse::class,
                'arguments' => [['service' => 'app.window']],
                'calls' => [
                    ['method' => 'addRoof', 'arguments' => [['service' => 'app.roof']]],
                ],
            ],
            'app.window' => ['class' => Window::class],
            'app.roof' => ['class' => Roof::class],
        ]);

        $report = ContainerInventoryReport::rendering($this->configDirectory, 'app.calls');

        // The detail opens with the identifier's own line and closes
        // with the final newline.
        self::assertStringStartsWith('app.calls', $report);
        self::assertStringEndsWith("\n", $report);
        self::assertStringContainsString('app.calls', $report);
        self::assertStringContainsString('shape:        class', $report);
        self::assertStringContainsString('class:        ' . CallsHouse::class, $report);
        self::assertStringContainsString('lifetime:     singleton', $report);
        self::assertStringContainsString('calls:        addRoof', $report);
        self::assertStringContainsString('lazy:         (eager)', $report);
        self::assertStringContainsString('dependencies: app.window, app.roof', $report);
    }

    public function testTheUnreferencedReportRidesTheHeader(): void
    {
        $this->declare([
            'app.window' => ['class' => Window::class],
            'app.house' => [
                'class' => House::class,
                'arguments' => [['service' => 'app.window'], 4, ['style' => 'georgian']],
            ],
            'app.lone' => ['value' => 'nobody references me'],
        ]);

        $report = ContainerInventoryReport::rendering($this->configDirectory);

        // The unreferenced report names in declaration order -- the
        // framework's own unreferenced registrations first, then the
        // host's: app.house (nothing references it) and app.lone.
        self::assertStringContainsString('unreferenced: http.errors', $report);
        self::assertStringContainsString('app.house, app.lone', $report);
    }

    public function testTheLazyMarksCloseTheTable(): void
    {
        $this->declare([
            'app.window' => ['class' => Window::class, 'lazy' => true],
        ]);

        $report = ContainerInventoryReport::rendering($this->configDirectory);

        self::assertStringContainsString('lazy marks -- 1', $report);
        self::assertStringContainsString("\n  app.window -> " . Window::class, $report);
    }

    public function testAQueriedIdentifierTheBootDidNotDeclareRefuses(): void
    {
        $this->declare(['app.window' => ['class' => Window::class]]);

        try {
            ContainerInventoryReport::rendering($this->configDirectory, 'app.absent');
            self::fail('Expected a ToolingException to be thrown.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                "The boot declared no service 'app.absent'.",
                $toolingException->getMessage(),
            );
        }
    }

    public function testTheLazyMarkRidesTheTableLine(): void
    {
        $this->declare([
            'app.window' => ['class' => Window::class, 'lazy' => true],
        ]);

        $report = ContainerInventoryReport::rendering($this->configDirectory);

        self::assertStringContainsString('app.window  class  singleton  ' . Window::class . '  lazy', $report);
    }

    /**
     * The alias shape rides its own arm of the table line: the
     * declaration's target, arrow-prefixed -- the shape column says
     * alias, the target column says where the identifier points. The
     * alias's own detail answers the target the declaration named.
     */
    public function testTheAliasEntryRidesItsOwnArm(): void
    {
        $this->declare([
            'app.window' => ['class' => Window::class],
            'app.pane' => ['alias' => 'app.window'],
        ]);

        $report = ContainerInventoryReport::rendering($this->configDirectory);

        self::assertStringContainsString("\n  app.pane  alias  singleton  -> app.window", $report);

        $detail = ContainerInventoryReport::rendering($this->configDirectory, 'app.pane');

        self::assertStringContainsString('shape:        alias', $detail);
        self::assertStringContainsString('alias:        app.window', $detail);
    }

    /**
     * The factory shape rides its own arm of the table line: the
     * declared callable, parenthesis-suffixed -- and its own detail
     * answers the callable the declaration named.
     */
    public function testTheFactoryEntryRidesItsOwnArm(): void
    {
        $this->declare([
            'app.fuel' => ['class' => EngineFuel::class],
            'app.engine' => [
                'factory' => EngineFactory::class . '::make',
                'arguments' => [['service' => 'app.fuel']],
            ],
        ]);

        $report = ContainerInventoryReport::rendering($this->configDirectory);

        self::assertStringContainsString(
            "\n  app.engine  factory  singleton  " . EngineFactory::class . '::make()',
            $report,
        );

        $detail = ContainerInventoryReport::rendering($this->configDirectory, 'app.engine');

        self::assertStringContainsString('shape:        factory', $detail);
        self::assertStringContainsString('factory:      ' . EngineFactory::class . '::make', $detail);
    }

    /**
     * The value shape's own row: the held type the else arm answers
     * -- no class, no alias, no factory, so the value column names the
     * type alone.
     */
    public function testTheValueEntryRidesItsOwnRow(): void
    {
        $this->declare([
            'app.answer' => ['value' => 42],
        ]);

        $report = ContainerInventoryReport::rendering($this->configDirectory);

        self::assertStringContainsString("\n  app.answer  value  singleton  int", $report);
    }

    /**
     * The lazy-marked entry's own detail: the mark's target class, not
     * the eager marker -- the same class the lazy view names.
     */
    public function testTheLazyMarkedDetailAnswersTheTargetClass(): void
    {
        $this->declare([
            'app.window' => ['class' => Window::class, 'lazy' => true],
        ]);

        $detail = ContainerInventoryReport::rendering($this->configDirectory, 'app.window');

        self::assertStringContainsString('lazy:         ' . Window::class, $detail);
        self::assertStringNotContainsString('lazy:         (eager)', $detail);
    }

    /**
     * The value shape's own detail: no class, no factory, no alias --
     * every unset field answers its own empty marker, and the value
     * column answers the held type.
     */
    public function testTheValueEntryAnswersItsUnsetFieldsInTheDetail(): void
    {
        $this->declare([
            'app.answer' => ['value' => 42],
        ]);

        $report = ContainerInventoryReport::rendering($this->configDirectory, 'app.answer');

        self::assertStringContainsString('shape:        value', $report);
        self::assertStringContainsString('class:        (none)', $report);
        self::assertStringContainsString('factory:      (none)', $report);
        self::assertStringContainsString('alias:        (none)', $report);
        self::assertStringContainsString('value:        int', $report);
        self::assertStringContainsString('tags:         (none)', $report);
        self::assertStringContainsString('calls:        (none)', $report);
        self::assertStringContainsString('dependencies: (none)', $report);
    }

    public function testTheThrowawayArtifactPathCarriesItsOwnShape(): void
    {
        $path = ContainerAnalysisArtifact::path();

        $basename = basename($path);

        // Sixteen hex characters: the `random_bytes` entropy a co-tenant of
        // the temporary directory cannot predict ahead of the allocation
        // (the clock-derived thirteen a `uniqid` answered were predictable
        // by anyone who could watch the clock).
        self::assertMatchesRegularExpression(
            '/^zef-container-inventory-[a-f0-9]{16}\.php$/',
            $basename,
        );
        self::assertSame(sys_get_temp_dir(), \dirname($path));
    }

    /**
     * Two allocations never collide: the throwaway seam's whole
     * promise -- two analyses in one temporary directory are two
     * files.
     */
    public function testTwoAllocationsNeverCollide(): void
    {
        self::assertNotSame(ContainerAnalysisArtifact::path(), ContainerAnalysisArtifact::path());
    }

    /**
     * The dump the analysis's own throwaway seam wrote is gone when
     * the report answers: an `aot.enabled` boot under the production
     * default dumps through the seam, and the report's finally leaves
     * no `zef-container-inventory-` artifact behind -- the temporary
     * directory carries none after the rendering.
     */
    public function testTheDumpedThrowawayArtifactNeverOutlivesTheReport(): void
    {
        $this->declare(['app.window' => ['class' => Window::class]]);
        file_put_contents(
            $this->configDirectory . '/aot.php',
            '<?php return ["enabled" => true];',
        );

        $report = ContainerInventoryReport::rendering($this->configDirectory);

        self::assertStringContainsString('app.window', $report);
        self::assertStringContainsString('boot: the artifact was absent', $report);
        self::assertSame([], glob(sys_get_temp_dir() . '/zef-container-inventory-*.php'));
    }

    /**
     * The declared configuration the analysis boots: one file, the
     * `services` key, the declaration grammar's own shape.
     *
     * @param array<string, array<string, mixed>> $services
     */
    private function declare(array $services): void
    {
        file_put_contents(
            $this->configDirectory . '/services.php',
            '<?php return ' . var_export($services, true) . ';',
        );
    }
}
