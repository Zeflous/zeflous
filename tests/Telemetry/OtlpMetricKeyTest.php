<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\MeterSlot;
use Zef\Framework\Telemetry\OtlpMetricKey;

/**
 * The OTLP lane's key split, on its own terms: the composite
 * snapshot key read back into the metric NAME the series was
 * recorded under -- the fold the JSON payload composes so the wire
 * carries the name the caller spelled, never the whole composite
 * (the issue #779 finding: a series recorded as
 * `zef.http.errors.total` under `["http.request.method" => "GET"]`
 * shipped as one garbage metric per series, its name the whole
 * `name|{...}` composite).
 *
 * The split's grammar is the grammar the key itself was minted by
 * ({@see MeterSlot}), so the pins read the two ends of the one
 * seam: a key the slot minted splits back to the name it was
 * minted from, a bare key (the hand-made maps the suite composes)
 * reads whole, and a name carrying a pipe of its own reads the
 * same off-vocabulary truncation the Prometheus lane's twin fold
 * answers ({@see \Zef\Framework\Telemetry\PrometheusSeries}) --
 * the framework-owned `zef.*` names never carry one.
 *
 * @internal
 */
#[CoversClass(OtlpMetricKey::class)]
final class OtlpMetricKeyTest extends TestCase
{
    public function testASlotMintedKeyAnswersTheNameItWasMintedFrom(): void
    {
        $key = MeterSlot::named('zef.http.errors.total', ['http.request.method' => 'GET'])->key;

        self::assertSame('zef.http.errors.total', OtlpMetricKey::name($key));
    }

    public function testABareKeyReadsWhole(): void
    {
        // The hand-made maps the suite composes carry bare names with
        // the attributes on the series -- the fold must read those as
        // the metric names they already are, not vanish them.
        self::assertSame('zef.requests', OtlpMetricKey::name('zef.requests'));
    }

    public function testASlotMintedKeyUnderTwoDimensionSetsAnswersTheOneName(): void
    {
        $get = MeterSlot::named('zef.http.errors.total', ['http.request.method' => 'GET'])->key;
        $post = MeterSlot::named('zef.http.errors.total', ['http.request.method' => 'POST'])->key;

        self::assertSame(
            OtlpMetricKey::name($get),
            OtlpMetricKey::name($post),
            'two series of the one name answer the one metric name',
        );
    }

    public function testANameCarryingItsOwnPipeTruncatesAtTheFirstSeparator(): void
    {
        // The off-vocabulary posture the Prometheus lane's twin fold
        // answers: the FIRST separator the grammar mints is the
        // split, so a name carrying a pipe of its own reads
        // truncated -- the framework-owned `zef.*` names never
        // carry one, and the split's one grammar is the grammar the
        // key itself was minted by.
        self::assertSame('zef.custom', OtlpMetricKey::name('zef.custom|name|{"a":"b"}'));
    }

    public function testTheMultibyteNameSplitsOnTheSeparator(): void
    {
        // The Prometheus lane's own twin pin ({@see
        // PrometheusRendererTest}'s multibyte lane): a multibyte
        // name keeps the character-offset discipline -- a
        // byte-offset fold would truncate the two-byte `é` mid-name,
        // the split landing at the pipe's byte spelling instead of
        // its character one.
        self::assertSame('métric', OtlpMetricKey::name('métric|{"k":"v"}'));
    }

    public function testTheEmptyKeyAnswersTheEmptyName(): void
    {
        self::assertSame('', OtlpMetricKey::name(''));
    }
}
