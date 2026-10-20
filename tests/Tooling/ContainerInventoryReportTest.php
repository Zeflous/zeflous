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
use Zef\Framework\Tooling\ContainerInventoryReport;
use Zef\Framework\Tooling\ToolingException;
use Zef\Test\Support\Container\Aot\CallsHouse;
use Zef\Test\Support\Container\Aot\House;
use Zef\Test\Support\Container\Aot\Roof;
use Zef\Test\Support\Container\Aot\Window;

/**
 * @internal
 */
#[CoversClass(ContainerInventoryReport::class)]
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
        self::assertStringContainsString('Container inventory -- ', $report);
        self::assertStringContainsString('boot: the aot knob is off', $report);
        self::assertStringContainsString('app.window  class  singleton  ' . Window::class, $report);
        self::assertStringContainsString(
            'app.house  class  singleton  ' . House::class . '  tags: frame.house  deps: app.window',
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
        self::assertStringContainsString('app.window -> ' . Window::class, $report);
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
