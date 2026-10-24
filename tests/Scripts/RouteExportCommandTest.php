<?php

declare(strict_types=1);

namespace Zef\Test\Scripts;

use Opis\JsonSchema\Errors\ValidationError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Process\Process;
use Zef\Test\Export\ExportSchemaFixture;

/**
 * Exercises the ecosystem export commands end to end as real
 * subprocesses, the same way a host's shell invokes them -- the route
 * inspection family's own discipline (`bin/zef route:*`): guarded
 * arguments, honest exit codes (0 the rendered document, 1 the
 * refused boot, 2 the usage or flag defect), the answer on the
 * standard output and the refusals on the standard error. The
 * rendered documents are themselves judged by the official
 * interchange schemas, not just eyeballed: what the CLI ships is
 * what the ecosystem can import.
 *
 * @internal
 */
#[CoversNothing]
final class RouteExportCommandTest extends TestCase
{
    private const string COMMAND = 'bin/zef';

    private const string FIXTURE = 'tests/Scripts/fixtures/configs';

    public function testTheFixtureTableAnswersItsOpenApiDocument(): void
    {
        [$exitCode, $output] = $this->runCommand([
            'route:openapi',
            self::FIXTURE,
            '--title=Fixture API',
            '--version=2.5.0',
        ]);

        $document = $this->decoded($output);

        self::assertSame(0, $exitCode);
        self::assertSame('3.1.0', $document['openapi']);
        self::assertSame(['title' => 'Fixture API', 'version' => '2.5.0'], $document['info']);
        self::assertSame([['url' => 'https://admin.example.com']], $document['servers']);
        $paths = $this->mapAt($document['paths']);
        self::assertSame(
            ['/users/{id}', '/users', '/admin/reports', '/legacy/status', '/ping'],
            array_keys($paths),
        );
        self::assertStringContainsString('"/users/{id}"', $output);
    }

    public function testTheFixtureTableAnswersAPostmanCollection(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:postman', self::FIXTURE, '--title=Fixture API']);

        $collection = $this->decoded($output);

        self::assertSame(0, $exitCode);
        $info = $this->mapAt($collection['info']);
        self::assertSame('Fixture API', $info['name']);
        self::assertSame(
            'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            $info['schema'],
        );
        $items = $collection['item'];
        self::assertIsArray($items);
        self::assertCount(6, $items);
        self::assertSame('users.show', $this->mapAt($items[0])['name']);
        self::assertSame('/ping', $this->mapAt($items[5])['name']);
    }

    public function testTheOpenApiDocumentValidatesAgainstTheOfficialSchema(): void
    {
        [, $output] = $this->runCommand([
            'route:openapi',
            self::FIXTURE,
            '--title=Fixture API',
            '--version=2.5.0',
        ]);

        $result = ExportSchemaFixture::validator()->validate(
            $this->roundTripped($output),
            ExportSchemaFixture::openApiSchema(),
        );

        self::assertTrue(
            $result->isValid(),
            "The CLI's OpenAPI document must validate against the official schema: "
            . ($result->error() instanceof ValidationError
                ? ExportSchemaFixture::leafSentence($result->error())
                : 'unknown refusal'),
        );
    }

    public function testThePostmanCollectionValidatesAgainstTheOfficialSchema(): void
    {
        [, $output] = $this->runCommand(['route:postman', self::FIXTURE, '--title=Fixture API']);

        $result = ExportSchemaFixture::validator()->validate(
            $this->roundTripped($output),
            ExportSchemaFixture::postmanSchema(),
        );

        self::assertTrue(
            $result->isValid(),
            "The CLI's Postman collection must validate against the official schema: "
            . ($result->error() instanceof ValidationError
                ? ExportSchemaFixture::leafSentence($result->error())
                : 'unknown refusal'),
        );
    }

    public function testTheSchemeFlagIsSpelledIntoTheServers(): void
    {
        [, $output] = $this->runCommand([
            'route:openapi',
            self::FIXTURE,
            '--title=Fixture API',
            '--version=2.5.0',
            '--scheme=http',
        ]);

        $document = $this->decoded($output);

        self::assertSame([['url' => 'http://admin.example.com']], $document['servers']);
    }

    public function testAMissingTitleIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:openapi', self::FIXTURE, '--version=2.5.0']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Usage: bin/zef route:openapi', $output);
    }

    public function testAMissingVersionIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:openapi', self::FIXTURE, '--title=Fixture API']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Invalid --version= value.', $output);
    }

    public function testAnEmptyTitleIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:postman', self::FIXTURE, '--title=']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Invalid --title= value.', $output);
    }

    public function testAnUnknownSchemeIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand([
            'route:openapi',
            self::FIXTURE,
            '--title=Fixture API',
            '--version=2.5.0',
            '--scheme=ftp',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Invalid --scheme= value.', $output);
    }

    public function testThePostmanCommandRefusesTheOpenApiFlags(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:postman', self::FIXTURE, '--title=X', '--version=2']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('bin/zef route:postman', $output);
    }

    public function testAnUnknownFlagIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand([
            'route:openapi',
            self::FIXTURE,
            '--title=Fixture API',
            '--version=2.5.0',
            '--json',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Usage: bin/zef route:openapi', $output);
    }

    public function testAMissingConfigurationDirectoryIsAUsageDefect(): void
    {
        [$exitCode, $output] = $this->runCommand(['route:openapi', 'tests/Scripts/fixtures/missing']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Configuration directory does not exist', $output);
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{int, string}
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
        self::assertIsArray($node);

        return $node;
    }

    /**
     * The command's standard output as the validator consumes it: the
     * JSON artifact the CLI ships, decoded back.
     */
    private function roundTripped(string $output): stdClass
    {
        $decoded = json_decode($output, false, 512, \JSON_THROW_ON_ERROR);

        self::assertInstanceOf(stdClass::class, $decoded);

        return $decoded;
    }
}
