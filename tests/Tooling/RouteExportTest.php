<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\CommandOutcome;
use Zef\Test\Support\Tooling\RouteExport;

/**
 * The route export verb as a pure outcome: the joined grammar's own
 * refusals (the missing or flag-shaped positional, the missing or
 * invalid required title, the openapi-only version and scheme
 * disciplines, the mis-scoped flags on the postman spelling), both
 * commands' answers (the OpenAPI 3.1 document, the Postman v2.1
 * collection), and the delegation to the inspection boot's own
 * router -- a refused boot surfacing with the export's own refusal
 * word, exactly the words the joined branch spelled before the lift.
 *
 * @internal
 */
#[CoversClass(RouteExport::class)]
#[CoversClass(CommandOutcome::class)]
final class RouteExportTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../Scripts/fixtures/configs';

    private const string USAGE = 'Usage: bin/zef route:openapi <config-directory> --title=<title>'
        . " --version=<api-version> [--scheme=http|https]\n"
        . "       bin/zef route:postman <config-directory> --title=<title>\n";

    public function testNoArgumentsAnswerTheUsageForBothCommands(): void
    {
        foreach (['route:openapi', 'route:postman'] as $command) {
            $outcome = RouteExport::run($command, []);

            self::assertSame(2, $outcome->exitCode, $command);
            self::assertSame('', $outcome->stdout, $command);
            self::assertSame(self::USAGE, $outcome->stderr, $command);
        }
    }

    public function testAFlagShapedPositionalAnswersTheUsage(): void
    {
        $outcome = RouteExport::run('route:openapi', ['--title=API']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(self::USAGE, $outcome->stderr);
    }

    public function testAMissingDirectoryIsAPathDefectNamingIt(): void
    {
        $outcome = RouteExport::run('route:openapi', ['/does/not/exist', '--title=API', '--version=1.0.0']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(
            "Configuration directory does not exist: /does/not/exist\n",
            $outcome->stderr,
        );
    }

    /**
     * The title is required for both spellings: the missing flag
     * answers its own named refusal followed by the usage.
     */
    public function testAMissingTitleIsAUsageDefect(): void
    {
        foreach (['route:openapi', 'route:postman'] as $command) {
            $arguments = $command === 'route:openapi'
                ? [self::FIXTURE, '--version=1.0.0']
                : [self::FIXTURE];

            $outcome = RouteExport::run($command, $arguments);

            self::assertSame(2, $outcome->exitCode, $command);
            self::assertSame("Invalid --title= value.\n" . self::USAGE, $outcome->stderr, $command);
        }
    }

    public function testAnEmptyTitleIsAUsageDefect(): void
    {
        $outcome = RouteExport::run('route:postman', [self::FIXTURE, '--title=']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame("Invalid --title= value.\n" . self::USAGE, $outcome->stderr);
    }

    public function testAControlByteTitleIsAUsageDefect(): void
    {
        $outcome = RouteExport::run('route:postman', [self::FIXTURE, "--title=API\x01"]);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame("Invalid --title= value.\n" . self::USAGE, $outcome->stderr);
    }

    public function testAnOverLongTitleIsAUsageDefect(): void
    {
        $outcome = RouteExport::run('route:postman', [self::FIXTURE, '--title=' . str_repeat('t', 257)]);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame("Invalid --title= value.\n" . self::USAGE, $outcome->stderr);
    }

    /**
     * The title bound's own upper edge: a value of exactly 256
     * characters passes the judgement and reaches the collection.
     */
    public function testATitleAtTheBoundReachesTheCollection(): void
    {
        $title = str_repeat('t', 256);

        $outcome = RouteExport::run('route:postman', [self::FIXTURE, '--title=' . $title]);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('"name": "' . $title . '"', $outcome->stdout);
    }

    /**
     * The bound's unit is the character: a 200-character multibyte
     * title (400 bytes) is within the bound and reaches the
     * collection, where a byte-counting spelling would refuse it
     * (the document spells the value in its escaped form).
     */
    public function testAMultibyteTitleWithinTheCharacterBoundReachesTheCollection(): void
    {
        $outcome = RouteExport::run('route:postman', [self::FIXTURE, '--title=' . str_repeat('é', 200)]);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString(
            '"name": "' . str_repeat('\u00e9', 200) . '"',
            $outcome->stdout,
        );
    }

    /**
     * The last --title= spelling wins, the collection answering the
     * second value.
     */
    public function testTheLastTitleSpellingWins(): void
    {
        $outcome = RouteExport::run(
            'route:postman',
            [self::FIXTURE, '--title=First', '--title=Second'],
        );

        self::assertSame(0, $outcome->exitCode);
        self::assertStringContainsString('"name": "Second"', $outcome->stdout);
        self::assertStringNotContainsString('"name": "First"', $outcome->stdout);
    }

    /**
     * The version is the openapi spelling's own required flag: its
     * missing, empty, over-long and control-byte spellings all answer
     * the named refusal.
     */
    public function testAnInvalidVersionIsAUsageDefect(): void
    {
        $cases = [
            [self::FIXTURE, '--title=API'],
            [self::FIXTURE, '--title=API', '--version='],
            [self::FIXTURE, '--title=API', '--version=' . str_repeat('v', 257)],
            [self::FIXTURE, '--title=API', "--version=1.0.0\x01"],
        ];

        foreach ($cases as $case) {
            $outcome = RouteExport::run('route:openapi', $case);

            self::assertSame(2, $outcome->exitCode, (string) json_encode($case));
            self::assertSame("Invalid --version= value.\n" . self::USAGE, $outcome->stderr);
        }
    }

    /**
     * The version bound's own upper edge: a value of exactly 256
     * characters passes the judgement and reaches the document.
     */
    public function testAVersionAtTheBoundReachesTheDocument(): void
    {
        $version = str_repeat('v', 256);

        $outcome = RouteExport::run('route:openapi', [self::FIXTURE, '--title=API', '--version=' . $version]);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('"version": "' . $version . '"', $outcome->stdout);
    }

    /**
     * The version bound's unit is the character: a 200-character
     * multibyte version (400 bytes) reaches the document, where a
     * byte-counting spelling would refuse it (the document spells
     * the value in its escaped form).
     */
    public function testAMultibyteVersionWithinTheCharacterBoundReachesTheDocument(): void
    {
        $version = str_repeat('é', 200);

        $outcome = RouteExport::run('route:openapi', [self::FIXTURE, '--title=API', '--version=' . $version]);

        self::assertSame(0, $outcome->exitCode);
        self::assertStringContainsString(
            '"version": "' . str_repeat('\u00e9', 200) . '"',
            $outcome->stdout,
        );
    }

    /**
     * The version and scheme flags are mis-scoped on the postman
     * spelling: both answer the usage, not a value.
     */
    public function testThePostmanSpellingRefusesTheOpenapiFlags(): void
    {
        foreach (
            [
                [self::FIXTURE, '--title=API', '--version=1.0.0'],
                [self::FIXTURE, '--title=API', '--scheme=http'],
            ] as $arguments
        ) {
            $outcome = RouteExport::run('route:postman', $arguments);

            self::assertSame(2, $outcome->exitCode, (string) json_encode($arguments));
            self::assertSame(self::USAGE, $outcome->stderr);
        }
    }

    public function testAnUnknownFlagAnswersTheUsage(): void
    {
        $outcome = RouteExport::run('route:openapi', [self::FIXTURE, '--title=API', '--version=1.0.0', '--json']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(self::USAGE, $outcome->stderr);
    }

    public function testAnInvalidSchemeIsAUsageDefect(): void
    {
        $outcome = RouteExport::run(
            'route:openapi',
            [self::FIXTURE, '--title=API', '--version=1.0.0', '--scheme=ftp'],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame("Invalid --scheme= value.\n" . self::USAGE, $outcome->stderr);
    }

    /**
     * The judgement order the joined branch spelled: the required
     * title is judged before the scheme, so an input carrying both
     * defects answers the title's refusal.
     */
    public function testTheTitleIsJudgedBeforeTheScheme(): void
    {
        $outcome = RouteExport::run('route:openapi', [self::FIXTURE, '--title=', '--scheme=ftp']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame("Invalid --title= value.\n" . self::USAGE, $outcome->stderr);
    }

    /**
     * The required version is judged before the scheme too: an input
     * carrying both defects answers the version's refusal.
     */
    public function testTheVersionIsJudgedBeforeTheScheme(): void
    {
        $outcome = RouteExport::run('route:openapi', [self::FIXTURE, '--title=API', '--scheme=ftp']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame("Invalid --version= value.\n" . self::USAGE, $outcome->stderr);
    }

    public function testTheOpenapiDocumentAnswersTheFixtureTable(): void
    {
        $outcome = RouteExport::run(
            'route:openapi',
            [self::FIXTURE, '--title=Fixture API', '--version=1.0.0'],
        );

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringEndsWith("\n", $outcome->stdout);

        $document = $this->mapAt(json_decode(mb_rtrim($outcome->stdout, "\n"), true));
        self::assertSame('3.1.0', $document['openapi']);
        $info = $this->mapAt($document['info']);
        self::assertSame('Fixture API', $info['title']);
        self::assertSame('1.0.0', $info['version']);
        self::assertArrayHasKey('/users/{id}', $this->mapAt($document['paths']));
    }

    /**
     * The scheme the document answers rides the flag's own spelling:
     * `http` rewrites the server URLs, the absent flag answering the
     * `https` default.
     */
    public function testTheSchemeFlagReachesTheDocument(): void
    {
        $outcome = RouteExport::run(
            'route:openapi',
            [self::FIXTURE, '--title=Fixture API', '--version=1.0.0', '--scheme=http'],
        );

        self::assertSame(0, $outcome->exitCode);

        $document = $this->mapAt(json_decode(mb_rtrim($outcome->stdout, "\n"), true));
        $servers = $this->mapAt($document['servers']);
        $firstServer = $this->mapAt($servers[0]);
        self::assertSame('http://admin.example.com', $firstServer['url']);
    }

    public function testThePostmanCollectionAnswersTheFixtureTable(): void
    {
        $outcome = RouteExport::run('route:postman', [self::FIXTURE, '--title=Fixture API']);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringEndsWith("\n", $outcome->stdout);

        $document = $this->mapAt(json_decode(mb_rtrim($outcome->stdout, "\n"), true));
        $info = $this->mapAt($document['info']);
        self::assertSame('Fixture API', $info['name']);
        $items = $this->mapAt($document['item']);
        $firstItem = $this->mapAt($items[0]);
        self::assertSame('users.show', $firstItem['name']);
    }

    /**
     * A table the boot refuses surfaces the full descriptive refusal
     * with exit 1 under the export's own refusal word.
     */
    public function testARefusedBootSurfacesTheDescriptiveRefusal(): void
    {
        $configDirectory = sys_get_temp_dir() . '/zef-export-verb-' . uniqid('', false);
        mkdir($configDirectory, 0o777, true);
        file_put_contents(
            $configDirectory . '/routes.php',
            '<?php return [["method" => ["GET"], "handler" => "UserController::show"]];',
        );

        try {
            $outcome = RouteExport::run('route:openapi', [$configDirectory, '--title=API', '--version=1.0.0']);

            self::assertSame(1, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertSame(
                'The export refused: Configuration failed schema validation:'
                . " path: expected a non-empty string, got missing.\n",
                $outcome->stderr,
            );
        } finally {
            unlink($configDirectory . '/routes.php');
            rmdir($configDirectory);
        }
    }

    /**
     * The decoded document node an assertion reads, narrowed to the
     * map shape the JSON form answers.
     *
     * @return array<array-key, mixed>
     */
    private function mapAt(mixed $node): array
    {
        if (!\is_array($node)) {
            self::fail('The document node the assertion read is not an object.');
        }

        return $node;
    }
}
