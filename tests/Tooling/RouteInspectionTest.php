<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\CommandOutcome;
use Zef\Test\Support\Tooling\RouteInspection;

/**
 * The route inspection verb as a pure outcome: the joined grammar's
 * own refusals (the missing or flag-shaped positional, the invalid
 * method or path, the unknown or mis-scoped flag, the over-bound
 * flag value), the three commands' answers (the table, the JSON
 * form, the compiled tree, the found match), the honest miss with
 * its exit 1, and the delegation to the inspection boot's own
 * router -- a refused boot surfacing with the boot's own chain,
 * exactly the words the joined branch spelled before the lift.
 *
 * @internal
 */
#[CoversClass(RouteInspection::class)]
#[CoversClass(CommandOutcome::class)]
final class RouteInspectionTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../Scripts/fixtures/configs';

    private const string USAGE = "Usage: bin/zef route:list <config-directory> [--json]\n"
        . '       bin/zef route:test <config-directory> <method> <path>'
        . " [--host=<host>] [--accept=<value>] [--version=<value>] [--json]\n"
        . "       bin/zef route:tree <config-directory>\n";

    public function testNoArgumentsAnswerTheUsageForEveryCommand(): void
    {
        foreach (['route:list', 'route:test', 'route:tree'] as $command) {
            $outcome = RouteInspection::run($command, []);

            self::assertSame(2, $outcome->exitCode, $command);
            self::assertSame('', $outcome->stdout, $command);
            self::assertSame(self::USAGE, $outcome->stderr, $command);
        }
    }

    public function testAFlagShapedPositionalAnswersTheUsage(): void
    {
        $outcome = RouteInspection::run('route:list', ['--json']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(self::USAGE, $outcome->stderr);
    }

    public function testAMissingDirectoryIsAPathDefectNamingIt(): void
    {
        $outcome = RouteInspection::run('route:list', ['/does/not/exist']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(
            "Configuration directory does not exist: /does/not/exist\n",
            $outcome->stderr,
        );
    }

    /**
     * The method positional is judged by the token grammar: the
     * empty spelling, a character outside the grammar and a
     * 33-character spelling are all defects naming the method.
     */
    public function testAnInvalidMethodIsAUsageDefectNamingIt(): void
    {
        foreach (['', 'GET/', str_repeat('G', 33)] as $method) {
            $outcome = RouteInspection::run('route:test', [self::FIXTURE, $method, '/users/42']);

            self::assertSame(2, $outcome->exitCode, $method);
            self::assertSame('', $outcome->stdout, $method);
            self::assertSame("Invalid request method: {$method}\n", $outcome->stderr, $method);
        }
    }

    /**
     * The path positional carries its own three rules: the leading
     * slash, the 2048-byte bound, the control-byte refusal.
     */
    public function testAnInvalidPathIsAUsageDefect(): void
    {
        foreach (['users/42', "/users/\x01", '/' . str_repeat('a', 2048)] as $path) {
            $outcome = RouteInspection::run('route:test', [self::FIXTURE, 'GET', $path]);

            self::assertSame(2, $outcome->exitCode, $path);
            self::assertSame('', $outcome->stdout, $path);
            self::assertSame(
                "The request path must start with a slash and carry no control bytes.\n",
                $outcome->stderr,
                $path,
            );
        }
    }

    /**
     * The method grammar's own anchor pin: the `/D` flag keeps the
     * dollar at the string's absolute end, so a method spelled with a
     * trailing newline is a defect even though PCRE's bare `$` would
     * accept it.
     */
    public function testATrailingNewlineMethodIsAUsageDefect(): void
    {
        $outcome = RouteInspection::run('route:test', [self::FIXTURE, "GET\n", '/users/42']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertStringStartsWith('Invalid request method: GET', $outcome->stderr);
    }

    /**
     * The method bound's own upper edge: a token of exactly 32
     * characters passes the grammar and reaches the router's own
     * judgement (an unknown verb answers the miss, not the usage).
     */
    public function testAMethodAtTheBoundReachesTheRouter(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, str_repeat('G', 32), '/users/42'],
        );

        self::assertNotSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('match ', $outcome->stdout);
    }

    /**
     * The path bound's own upper edge: a path of exactly 2048
     * characters passes the judgement and reaches the router's own
     * miss.
     */
    public function testAPathAtTheBoundReachesTheRouter(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, 'GET', '/' . str_repeat('a', 2047)],
        );

        self::assertNotSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('match ', $outcome->stdout);
    }

    /**
     * The bound's unit is the character: a 1501-character multibyte
     * path (3001 bytes) is within the 2048-character bound and
     * reaches the router, where a byte-counting spelling would
     * refuse it.
     */
    public function testAMultibytePathWithinTheCharacterBoundReachesTheRouter(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, 'GET', '/' . str_repeat('é', 1500)],
        );

        self::assertNotSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('match ', $outcome->stdout);
    }

    public function testAnUnknownFlagAnswersTheUsage(): void
    {
        $outcome = RouteInspection::run('route:list', [self::FIXTURE, '--bogus']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(self::USAGE, $outcome->stderr);
    }

    /**
     * The route:test-only flags are mis-scoped on the other
     * commands: `route:list --host=` answers the usage, not a value.
     */
    public function testAMisScopedHostFlagAnswersTheUsage(): void
    {
        $outcome = RouteInspection::run('route:list', [self::FIXTURE, '--host=admin.example.com']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(self::USAGE, $outcome->stderr);
    }

    /**
     * The same scope judgement for the accept flag: `route:list
     * --accept=` answers the usage, not a value.
     */
    public function testAMisScopedAcceptFlagAnswersTheUsage(): void
    {
        $outcome = RouteInspection::run('route:list', [self::FIXTURE, '--accept=text/html']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(self::USAGE, $outcome->stderr);
    }

    /**
     * The same scope judgement for the version flag: `route:list
     * --version=` answers the usage, not a value.
     */
    public function testAMisScopedVersionFlagAnswersTheUsage(): void
    {
        $outcome = RouteInspection::run('route:list', [self::FIXTURE, '--version=2']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(self::USAGE, $outcome->stderr);
    }

    /**
     * The `--json` flag collects by prefix in the first pass and is
     * judged by exact spelling in the second: `--jsonx` answers the
     * usage, never the JSON form.
     */
    public function testAJsonPrefixThatIsNotTheExactSpellingAnswersTheUsage(): void
    {
        $outcome = RouteInspection::run('route:list', [self::FIXTURE, '--jsonx']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(self::USAGE, $outcome->stderr);
    }

    /**
     * The tree owns no `--json` flag: the exact spelling is refused
     * by the second pass's scope judgement.
     */
    public function testTheTreeRefusesTheJsonFlagItDoesNotOwn(): void
    {
        $outcome = RouteInspection::run('route:tree', [self::FIXTURE, '--json']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame(self::USAGE, $outcome->stderr);
    }

    /**
     * The route:test values are judged against their own bounds: the
     * empty spelling is a defect, and each flag name appears in its
     * own refusal.
     */
    public function testAnEmptyHostValueIsAUsageDefect(): void
    {
        $outcome = RouteInspection::run('route:test', [self::FIXTURE, 'GET', '/users/42', '--host=']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("Invalid --host= value.\n", $outcome->stderr);
    }

    public function testAnOverLongHostValueIsAUsageDefect(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, 'GET', '/users/42', '--host=' . str_repeat('h', 254)],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame("Invalid --host= value.\n", $outcome->stderr);
    }

    /**
     * The bound itself is pinned from both sides: a host of exactly
     * the 253-character bound passes the judgement and reaches the
     * router (the host-agnostic route still answering the match).
     */
    public function testAHostAtTheBoundReachesTheRouter(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, 'GET', '/users/42', '--host=' . str_repeat('h', 253)],
        );

        self::assertNotSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('match found', $outcome->stdout);
    }

    /**
     * The host bound's unit is the character: a 200-character
     * multibyte host (400 bytes) is within the bound and reaches the
     * router, where a byte-counting spelling would refuse it.
     */
    public function testAMultibyteHostWithinTheCharacterBoundReachesTheRouter(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, 'GET', '/users/42', '--host=' . str_repeat('é', 200)],
        );

        self::assertNotSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('match found', $outcome->stdout);
    }

    public function testAControlByteAcceptValueIsAUsageDefect(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, 'GET', '/users/42', "--accept=text/html\x01"],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame("Invalid --accept= value.\n", $outcome->stderr);
    }

    /**
     * The accept bound's own upper edge: a value of exactly 256
     * characters passes the judgement and reaches the negotiation.
     */
    public function testAnAcceptValueAtTheBoundReachesTheRouter(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, 'GET', '/users/42', '--accept=' . str_repeat('a', 256)],
        );

        self::assertNotSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
    }

    /**
     * The accept bound's unit is the character: a 200-character
     * multibyte value (400 bytes) is within the bound and reaches the
     * negotiation, where a byte-counting spelling would refuse it.
     */
    public function testAMultibyteAcceptWithinTheCharacterBoundReachesTheRouter(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, 'GET', '/users/42', '--accept=' . str_repeat('é', 200)],
        );

        self::assertNotSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
    }

    public function testAnOverLongVersionValueIsAUsageDefect(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, 'GET', '/users/42', '--version=' . str_repeat('v', 257)],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame("Invalid --version= value.\n", $outcome->stderr);
    }

    /**
     * The version bound's own upper edge: a value of exactly 256
     * characters passes the judgement and reaches the selection.
     */
    public function testAVersionValueAtTheBoundReachesTheRouter(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, 'GET', '/users/42', '--version=' . str_repeat('v', 256)],
        );

        self::assertNotSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
    }

    /**
     * The version bound's unit is the character: a 200-character
     * multibyte value (400 bytes) is within the bound and reaches the
     * selection, where a byte-counting spelling would refuse it.
     */
    public function testAMultibyteVersionWithinTheCharacterBoundReachesTheRouter(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, 'GET', '/users/42', '--version=' . str_repeat('é', 200)],
        );

        self::assertNotSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
    }

    public function testTheFixtureTableAnswersItsRouteList(): void
    {
        $outcome = RouteInspection::run('route:list', [self::FIXTURE]);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('Route list -- 5 routes', $outcome->stdout);
        self::assertStringContainsString('users.show  GET  /users/{id}  UserController::show', $outcome->stdout);
    }

    public function testTheJsonFlagAnswersTheMachineForm(): void
    {
        $outcome = RouteInspection::run('route:list', [self::FIXTURE, '--json']);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringEndsWith("\n", $outcome->stdout);

        $document = json_decode(mb_rtrim($outcome->stdout, "\n"), true);
        self::assertIsArray($document);
        self::assertSame(5, $document['count']);
    }

    public function testTheTreeAnswersTheCompiledGraph(): void
    {
        $outcome = RouteInspection::run('route:tree', [self::FIXTURE]);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('Route tree -- the compiled graph', $outcome->stdout);
        self::assertStringContainsString('(root)', $outcome->stdout);
        self::assertStringContainsString('route /users/{id}', $outcome->stdout);
    }

    public function testAFoundMatchAnswersTheRouteAndExitsZero(): void
    {
        $outcome = RouteInspection::run('route:test', [self::FIXTURE, 'GET', '/users/42']);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('match found', $outcome->stdout);
        self::assertStringContainsString('  route:       users.show', $outcome->stdout);
        self::assertStringContainsString('  parameter:   id = 42', $outcome->stdout);
    }

    public function testARefusingMatchAnswersTheExplanationAndExitsOne(): void
    {
        $outcome = RouteInspection::run('route:test', [self::FIXTURE, 'DELETE', '/users']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('match method_not_allowed', $outcome->stdout);
        self::assertStringContainsString('  allowed:     GET,POST', $outcome->stdout);
    }

    public function testAPathMissAnswersTheNeighborhoodAndExitsOne(): void
    {
        $outcome = RouteInspection::run('route:test', [self::FIXTURE, 'GET', '/nope']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('match not_found', $outcome->stdout);
        self::assertStringContainsString('  stage:       path', $outcome->stdout);
    }

    public function testTheMatchAnswersTheToolIntegrationJson(): void
    {
        $outcome = RouteInspection::run('route:test', [self::FIXTURE, 'GET', '/users/42', '--json']);

        self::assertSame(0, $outcome->exitCode);
        self::assertStringEndsWith("\n", $outcome->stdout);

        $document = json_decode(mb_rtrim($outcome->stdout, "\n"), true);
        self::assertIsArray($document);
        self::assertSame('found', $document['outcome']);
        self::assertSame(['id' => '42'], $document['parameters']);
    }

    public function testTheSimulationFlagsReachTheMatch(): void
    {
        $outcome = RouteInspection::run(
            'route:test',
            [self::FIXTURE, 'GET', '/admin/reports', '--host=admin.example.com', '--version=2'],
        );

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('match found', $outcome->stdout);
        self::assertStringContainsString('  route:       reports.show', $outcome->stdout);
    }

    /**
     * A table the boot refuses surfaces the full descriptive refusal
     * with exit 1 -- the operator's window carries the chain the
     * boot walked.
     */
    public function testARefusedBootSurfacesTheDescriptiveRefusal(): void
    {
        $configDirectory = sys_get_temp_dir() . '/zef-route-verb-' . uniqid('', false);
        mkdir($configDirectory, 0o777, true);
        file_put_contents(
            $configDirectory . '/routes.php',
            '<?php return [["method" => ["GET"], "handler" => "UserController::show"]];',
        );

        try {
            $outcome = RouteInspection::run('route:list', [$configDirectory]);

            self::assertSame(1, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertSame(
                'The route boot refused: Configuration failed schema validation:'
                . " path: expected a non-empty string, got missing.\n",
                $outcome->stderr,
            );
        } finally {
            unlink($configDirectory . '/routes.php');
            rmdir($configDirectory);
        }
    }
}
