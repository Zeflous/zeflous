<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\CorrelationRedaction;

/**
 * The attribute table's redaction, on its own terms: the digest
 * census's every fragment, the deterministic digests, the marker
 * for the non-string sensitive values, and the untouched pass-through
 * of the plain table.
 *
 * @internal
 */
#[CoversClass(CorrelationRedaction::class)]
final class CorrelationRedactionTest extends TestCase
{
    public function testRedactsTheSensitiveKeysAsStableDigests(): void
    {
        $redacted = CorrelationRedaction::redacted([
            'authorization.token' => 'secret-token',
            'operation.class' => 'order',
            'idempotency' => true,
        ]);

        self::assertSame('order', $redacted['operation.class']);
        self::assertSame('sha256:' . hash('sha256', 'secret-token'), $redacted['authorization.token']);
        self::assertSame('[REDACTED]', $redacted['idempotency'], 'a non-string sensitive value redacts as the marker');
    }

    public function testTheRedactionIsDeterministic(): void
    {
        $first = CorrelationRedaction::redacted(['api-key' => 'same-value']);
        $second = CorrelationRedaction::redacted(['api-key' => 'same-value']);

        self::assertSame($first, $second, 'the same value answers the same digest every time');
        self::assertNotSame(
            CorrelationRedaction::redacted(['api-key' => 'other-value'])['api-key'],
            $first['api-key'],
            'a different value answers a different digest',
        );
    }

    #[DataProvider('provideEverySensitiveFragmentRedactsCases')]
    public function testEverySensitiveFragmentRedacts(string $key): void
    {
        $redacted = CorrelationRedaction::redacted([$key => 'value']);

        self::assertSame('sha256:' . hash('sha256', 'value'), $redacted[$key]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEverySensitiveFragmentRedactsCases(): iterable
    {
        yield 'authorization' => ['authorization'];

        yield 'cookie' => ['request.cookie.id'];

        yield 'token' => ['auth.token'];

        yield 'secret' => ['client-secret'];

        yield 'password' => ['user.password.hash'];

        yield 'credential' => ['credential.material'];

        yield 'body' => ['request.body.summary'];

        yield 'idempotency' => ['idempotency.key'];

        yield 'uppercase spelling' => ['AUTHORIZATION'];
    }

    public function testTheFoldOfTheKelvinSignFindsTheSensitiveFragment(): void
    {
        // The kelvin sign (U+212A) lower-cases into the ASCII k
        // through the Unicode case map alone -- the one spelling whose
        // fold the byte-level strtolower cannot reproduce, and the
        // only single-character lower-case landing in ASCII the map
        // carries.
        $redacted = CorrelationRedaction::redacted(["to\u{212A}en" => 'value']);

        self::assertSame(
            'sha256:' . hash('sha256', 'value'),
            $redacted["to\u{212A}en"],
            'the Unicode fold reads the fragment the byte-level fold cannot',
        );
    }

    public function testThePlainTablePassesThroughTheRedactionUntouched(): void
    {
        $attributes = ['operation.class' => 'order', 'status' => 200];

        self::assertSame($attributes, CorrelationRedaction::redacted($attributes));
    }

    public function testTheRedactionPreservesTheTablesOwnOrder(): void
    {
        $redacted = CorrelationRedaction::redacted([
            'authorization.token' => 'secret-token',
            'operation.class' => 'order',
            'status' => 200,
        ]);

        self::assertSame(
            [
                'authorization.token' => 'sha256:' . hash('sha256', 'secret-token'),
                'operation.class' => 'order',
                'status' => 200,
            ],
            $redacted,
        );
    }
}
