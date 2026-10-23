<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Routing\RouteMatchExplanation;
use Zef\Framework\Router\Router;
use Zef\Test\Support\Tooling\RouteMatchAnswer;
use Zef\Test\Support\Tooling\RouteMatchJson;
use Zef\Test\Support\Tooling\RouteMatchText;

/**
 * The route test command's presentation family: the answer ({@see
 * RouteMatchAnswer} -- the match's own data and the staged
 * explanation of why, one outcome vocabulary) with its text ({@see
 * RouteMatchText}) and JSON ({@see RouteMatchJson}) forms.
 *
 * @internal
 */
#[CoversClass(RouteMatchAnswer::class)]
#[CoversClass(RouteMatchText::class)]
#[CoversClass(RouteMatchJson::class)]
final class RouteMatchTest extends TestCase
{
    public function testTheAnswerCarriesTheMatchDataAndTheExplanationPair(): void
    {
        $answer = RouteMatchAnswer::answered($this->router(), 'GET', '/users/42');

        self::assertSame(RouteMatchExplanation::OUTCOME_FOUND, $answer->outcome);
        self::assertSame('users.show', $answer->matchResult->route()->name());
        self::assertSame(['id' => '42'], $answer->matchResult->parameters());
        self::assertTrue($answer->explanation->isFound());
    }

    public function testTheFoundAnswerSpellsTheRouteAndItsCapturedParameters(): void
    {
        $answer = RouteMatchAnswer::answered($this->router(), 'GET', '/users/42');

        self::assertSame(
            "match found\n"
            . "  route:       users.show\n"
            . "  pattern:     /users/{id}\n"
            . "  methods:     GET\n"
            . "  handler:     UserController::show\n"
            . "  parameter:   id = 42\n",
            RouteMatchText::rendered($answer),
        );
    }

    public function testTheFoundAnswerSpellsTheHostClaimItMatched(): void
    {
        $answer = RouteMatchAnswer::answered(
            $this->router(),
            'GET',
            '/admin/reports',
            'admin.example.com',
            'application/json',
            '2',
        );

        self::assertSame(
            "match found\n"
            . "  route:       reports.show\n"
            . "  pattern:     /admin/reports\n"
            . "  host:        admin.example.com\n"
            . "  methods:     GET\n"
            . "  handler:     ReportController::show\n",
            RouteMatchText::rendered($answer),
        );
    }

    public function testAMethodNotAllowedAnswerSpellsTheAllowedMethods(): void
    {
        $answer = RouteMatchAnswer::answered($this->router(), 'DELETE', '/users');

        self::assertSame(
            "match method_not_allowed\n"
            . "  stage:       method\n"
            . "  survivors:   path 1, host 1, version 1\n"
            . "  allowed:     GET,POST\n",
            RouteMatchText::rendered($answer),
        );
    }

    public function testAPathMissSpellsTheStageAndTheNeighborhood(): void
    {
        $answer = RouteMatchAnswer::answered($this->router(), 'GET', '/nope');

        self::assertSame(
            "match not_found\n"
            . "  stage:       path\n"
            . "  survivors:   path 0, host 0, version 0\n"
            . "  prefix:      /\n"
            . "  candidate:   /users\n"
            . "  candidate:   /admin/reports @admin.example.com\n"
            . "  candidate:   /legacy/status\n"
            . "  candidate:   /ping\n"
            . "  candidate:   /users/{id}\n",
            RouteMatchText::rendered($answer),
        );
    }

    public function testANotAcceptableAnswerSpellsTheProducibleMediaTypes(): void
    {
        $answer = RouteMatchAnswer::answered(
            $this->router(),
            'GET',
            '/admin/reports',
            'admin.example.com',
            'text/html',
            '2',
        );

        self::assertSame(
            "match not_acceptable\n"
            . "  stage:       accept\n"
            . "  survivors:   path 1, host 1, version 1\n"
            . "  producible:  application/json\n",
            RouteMatchText::rendered($answer),
        );
    }

    public function testTheJsonAnswerCarriesTheStableSchemaAcrossOutcomes(): void
    {
        $found = $this->decoded(
            RouteMatchJson::rendered(RouteMatchAnswer::answered($this->router(), 'GET', '/users/42')),
        );

        self::assertSame('found', $found['outcome']);

        $foundRoute = $this->mapAt($found['route']);

        self::assertSame('users.show', $foundRoute['name']);
        self::assertSame('/users/{id}', $foundRoute['path']);
        self::assertSame(['id' => '42'], $found['parameters']);

        $foundExplanation = $this->mapAt($found['explanation']);

        self::assertNull($foundExplanation['failedStage']);
        self::assertSame(1, $foundExplanation['pathSurvivors']);
        self::assertSame(1, $foundExplanation['hostSurvivors']);
        self::assertSame(1, $foundExplanation['versionSurvivors']);
        self::assertSame('', $foundExplanation['sharedPrefix']);

        $miss = $this->decoded(
            RouteMatchJson::rendered(RouteMatchAnswer::answered($this->router(), 'DELETE', '/users')),
        );

        self::assertSame('method_not_allowed', $miss['outcome']);
        self::assertNull($miss['route']);
        self::assertNull($miss['parameters']);

        $missExplanation = $this->mapAt($miss['explanation']);

        self::assertSame('method', $missExplanation['failedStage']);
        self::assertSame(1, $missExplanation['pathSurvivors']);
        self::assertSame(1, $missExplanation['hostSurvivors']);
        self::assertSame(1, $missExplanation['versionSurvivors']);
        self::assertSame('', $missExplanation['sharedPrefix']);
        self::assertSame(['GET', 'POST'], $missExplanation['allowedMethods']);
        self::assertSame([], $missExplanation['candidatePatterns']);

        $notAcceptable = $this->decoded(
            RouteMatchJson::rendered(RouteMatchAnswer::answered(
                $this->router(),
                'GET',
                '/admin/reports',
                'admin.example.com',
                'text/html',
                '2',
            )),
        );

        self::assertSame('not_acceptable', $notAcceptable['outcome']);

        $refusalExplanation = $this->mapAt($notAcceptable['explanation']);

        self::assertSame('accept', $refusalExplanation['failedStage']);
        self::assertSame(1, $refusalExplanation['pathSurvivors']);
        self::assertSame(1, $refusalExplanation['hostSurvivors']);
        self::assertSame(1, $refusalExplanation['versionSurvivors']);
        self::assertSame('', $refusalExplanation['sharedPrefix']);
        self::assertSame(['application/json'], $refusalExplanation['producibleMediaTypes']);
    }

    /**
     * A path miss is the explanation family's deepest answer: the
     * survivors collapse to zero, the shared prefix spells the
     * deepest static run the walk reached, and the neighborhood
     * carries the candidate patterns the operator can compare
     * against -- the JSON view answers everything the text form
     * spells, in the schema tools read.
     */
    public function testAPathMissAnswersTheFullExplanationSchema(): void
    {
        $document = $this->decoded(
            RouteMatchJson::rendered(RouteMatchAnswer::answered($this->router(), 'GET', '/nope')),
        );

        self::assertSame('not_found', $document['outcome']);

        $explanation = $this->mapAt($document['explanation']);

        self::assertSame('path', $explanation['failedStage']);
        self::assertSame(0, $explanation['pathSurvivors']);
        self::assertSame(0, $explanation['hostSurvivors']);
        self::assertSame(0, $explanation['versionSurvivors']);
        self::assertSame('/', $explanation['sharedPrefix']);
        self::assertSame(
            ['/users', '/admin/reports @admin.example.com', '/legacy/status', '/ping', '/users/{id}'],
            $explanation['candidatePatterns'],
        );
        self::assertSame([], $explanation['allowedMethods']);
        self::assertSame([], $explanation['producibleMediaTypes']);
    }

    private function router(): Router
    {
        $table = require \dirname(__DIR__, 2) . '/tests/Scripts/fixtures/configs/routes.php';

        if (!\is_array($table)) {
            self::fail('The fixture route table did not answer a list.');
        }

        return Router::fromRoutes($table);
    }

    /**
     * The rendered answer, decoded and shaped to the map the
     * assertions read through.
     *
     * @return array<array-key, mixed>
     */
    private function decoded(string $json): array
    {
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            self::fail('The rendered answer did not decode to an object.');
        }

        return $decoded;
    }

    /**
     * One answer node the assertions read containers through,
     * shaped to the map it must be before any offset touches it.
     *
     * @return array<array-key, mixed>
     */
    private function mapAt(mixed $node): array
    {
        if (!\is_array($node)) {
            self::fail('The answer node the assertion read is not an object.');
        }

        return $node;
    }
}
