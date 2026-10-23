<?php

declare(strict_types=1);

namespace Zef\Test\Scripts;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Exercises the route inspection commands end to end as real
 * subprocesses, the same way a host's shell invokes them -- the
 * errors lane's own CLI family discipline (`bin/zef errors:*`) joined
 * by the container analysis command: guarded arguments, honest exit
 * codes (0 the found answer, 1 the refused boot or the refusing
 * match outcome, 2 the usage or path defect), the answer on the
 * standard output and the refusals on the standard error.
 *
 * @internal
 */
#[CoversNothing]
final class RouteCommandTest extends TestCase
{
    private const string COMMAND = 'bin/zef';

    private const string FIXTURE = 'tests/Scripts/fixtures/configs';

    public function testTheFixtureTableAnswersItsRouteList(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:list', self::FIXTURE]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Route list -- 5 routes', $output);
        self::assertStringContainsString(
            'users.show  GET  /users/{id}  UserController::show',
            $output,
        );
        self::assertStringContainsString(
            'reports.show  GET  /admin/reports @admin.example.com  ReportController::show'
            . '  version: 2  produces: application/json  middleware: auth.session',
            $output,
        );
        self::assertStringContainsString(
            'status.legacy  GET  /legacy/status  StatusController::show'
            . '  deprecated: 2026-01-15, sunset: 2026-06-30, link: /docs/migration',
            $output,
        );
        self::assertStringContainsString('(unnamed)  GET  /ping  PingController::pong', $output);
    }

    public function testTheRouteListAnswersTheToolIntegrationJson(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:list', self::FIXTURE, '--json']);

        $document = $this->decoded($output);

        self::assertSame(0, $exitCode);
        self::assertSame(5, $document['count']);

        $firstRoute = $this->mapAt($this->mapAt($document['routes'])[0]);

        self::assertSame('users.show', $firstRoute['name']);

        $legacyRoute = $this->mapAt($this->mapAt($document['routes'])[3]);

        self::assertSame(
            '2026-01-15T00:00:00Z',
            $this->mapAt($legacyRoute['deprecation'])['date'],
        );
        self::assertStringContainsString('"/users/{id}"', $output);
    }

    public function testTheShippedProfileAnswersTheZeroRoutingList(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:list', 'app/configs']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Route list -- 0 routes', $output);
    }

    public function testAFoundMatchAnswersTheRouteAndExitsZero(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:test', self::FIXTURE, 'GET', '/users/42']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('match found', $output);
        self::assertStringContainsString('  route:       users.show', $output);
        self::assertStringContainsString('  parameter:   id = 42', $output);
    }

    public function testARefusingMatchAnswersTheExplanationAndExitsOne(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:test', self::FIXTURE, 'DELETE', '/users']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('match method_not_allowed', $output);
        self::assertStringContainsString('  allowed:     GET,POST', $output);
    }

    public function testAPathMissAnswersTheNeighborhoodAndExitsOne(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:test', self::FIXTURE, 'GET', '/nope']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('match not_found', $output);
        self::assertStringContainsString('  stage:       path', $output);
        self::assertStringContainsString('  candidate:   /users/{id}', $output);
    }

    public function testANegotiatedRefusalAnswersWithTheSimulationFlags(): void
    {
        [$exitCode, $output] = $this->runCommand([
            'route:test',
            self::FIXTURE,
            'GET',
            '/admin/reports',
            '--host=admin.example.com',
            '--version=2',
            '--accept=text/html',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('match not_acceptable', $output);
        self::assertStringContainsString('  producible:  application/json', $output);
    }

    public function testTheMatchAnswersTheToolIntegrationJson(): void
    {
        [$exitCode, $output] = $this->runCommand(
            ['route:test', self::FIXTURE, 'GET', '/users/42', '--json'],
        );

        $document = $this->decoded($output);

        self::assertSame(0, $exitCode);
        self::assertSame('found', $document['outcome']);
        self::assertSame('users.show', $this->mapAt($document['route'])['name']);
        self::assertSame(['id' => '42'], $document['parameters']);
    }

    public function testTheTreeAnswersTheCompiledGraph(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:tree', self::FIXTURE]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Route tree -- the compiled graph', $output);
        self::assertStringContainsString('(root)', $output);
        self::assertStringContainsString('{parameter}', $output);
        self::assertStringContainsString('route /users/{id}', $output);
    }

    public function testAMissingConfigurationDirectoryIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:list', '/does/not/exist']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Configuration directory does not exist: /does/not/exist', $output);
    }

    public function testAnAbsentArgumentVectorIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:list']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'Usage: bin/zef route:list <config-directory> [--json]',
            $output,
        );
    }

    public function testAnUnknownFlagIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:list', self::FIXTURE, '--bogus']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Usage: bin/zef route:list', $output);
    }

    public function testTheTreeRefusesTheJsonFlagItDoesNotOwn(): void
    {
        [$exitCode] = $this->runCommand(['route:tree', self::FIXTURE, '--json']);

        self::assertSame(2, $exitCode);
    }

    public function testANonTokenMethodIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:test', self::FIXTURE, 'BAD METHOD', '/users']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Invalid request method: BAD METHOD', $output);
    }

    public function testAPathWithoutTheLeadingSlashIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:test', self::FIXTURE, 'GET', 'users']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString(
            'The request path must start with a slash and carry no control bytes.',
            $output,
        );
    }

    public function testAnEmptyFlagValueIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand(
            ['route:test', self::FIXTURE, 'GET', '/users', '--host='],
        );

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Invalid --host= value.', $output);
    }

    /**
     * A declaration the boot refuses surfaces the full descriptive
     * refusal on STDERR with exit 1 -- the operator's window carries
     * the typed refusal the configuration system answered.
     */
    public function testARefusedBootSurfacesTheDescriptiveRefusal(): void
    {
        $configDirectory = sys_get_temp_dir() . '/zef-route-cli-' . uniqid('', false);
        mkdir($configDirectory, 0o777, true);
        file_put_contents(
            $configDirectory . '/routes.php',
            '<?php return [["path" => "no-slash", "method" => ["GET"], "handler" => "X::y"]];',
        );

        try {
            [$exitCode, $output] = $this->runCommand(['route:list', $configDirectory]);

            self::assertSame(1, $exitCode);
            self::assertStringContainsString('The route boot refused:', $output);
            self::assertStringContainsString(
                'The route path "no-slash" must start with a slash.',
                $output,
            );
        } finally {
            unlink($configDirectory . '/routes.php');
            rmdir($configDirectory);
        }
    }

    /**
     * The answer rides the standard output and the refusal the
     * standard error -- the streams a host's shell pipes separately.
     */
    public function testTheAnswerAndTheRefusalRideTheirOwnStreams(): void
    {
        $process = new Process(
            [\PHP_BINARY, \dirname(__DIR__, 2) . '/' . self::COMMAND, 'route:test', self::FIXTURE, 'GET', '/nope'],
            \dirname(__DIR__, 2),
        );
        $process->run();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('match not_found', $process->getOutput());
        self::assertSame('', $process->getErrorOutput());
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0: int, 1: string}
     */
    private function runCommand(array $arguments): array
    {
        $process = new Process(
            [\PHP_BINARY, \dirname(__DIR__, 2) . '/' . self::COMMAND, ...$arguments],
            \dirname(__DIR__, 2),
        );
        $process->run();

        return [$process->getExitCode() ?? 255, $process->getOutput() . $process->getErrorOutput()];
    }

    /**
     * The command's standard output, decoded and shaped to the map
     * the assertions read through.
     *
     * @return array<array-key, mixed>
     */
    private function decoded(string $output): array
    {
        $decoded = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            self::fail('The command output did not decode to an object.');
        }

        return $decoded;
    }

    /**
     * One document node the assertions read containers through,
     * shaped to the map it must be before any offset touches it.
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
