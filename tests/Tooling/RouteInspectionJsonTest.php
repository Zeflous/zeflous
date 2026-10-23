<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\RouteInspectionJson;

/**
 * The inspection commands' shared JSON encoding: pretty for the
 * terminal, forward slashes unescaped (route patterns are full of
 * them), throw-on-error so an encoding failure surfaces instead of a
 * silent false.
 *
 * @internal
 */
#[CoversClass(RouteInspectionJson::class)]
final class RouteInspectionJsonTest extends TestCase
{
    public function testTheEncodingIsPrettyAndKeepsForwardSlashes(): void
    {
        $encoded = RouteInspectionJson::encode(['path' => '/users/{id}']);

        self::assertSame(
            "{\n    \"path\": \"/users/{id}\"\n}",
            $encoded,
        );
    }

    public function testAnEncodingFailureThrowsInsteadOfAnsweringFalse(): void
    {
        $this->expectException(JsonException::class);

        RouteInspectionJson::encode(['invalid' => "\xB1\x31"]);
    }
}
