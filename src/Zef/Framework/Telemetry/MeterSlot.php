<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use JsonException;

/**
 * One recording's resolved series identity: the composite key and
 * the normalised attribute map it was minted from -- {@see
 * CounterMeter}'s own unit, extracted so the bound judgement and the
 * key grammar each stay one named place the suite reads directly.
 *
 * The key grammar is the reference's own composite spelling: the
 * metric name, the `|` separator, the JSON rendering of the ordered
 * attribute map (the flags keeping the slash unescaped and the
 * Unicode verbatim, so the key is the identity the exposition's own
 * renderer can split back on the separator). The rendering is
 * deterministic because the map is ksorted before it arrives here --
 * two recordings of the same dimensions under the same name mint the
 * same key however the caller ordered the map.
 */
final readonly class MeterSlot
{
    /** @param array<string,mixed> $attributes */
    private function __construct(
        public string $key,
        public array $attributes,
    ) {
    }

    /**
     * Mints the named series' identity: the composite key over the
     * name and its normalised attributes.
     *
     * @param string              $name       the metric name
     * @param array<string,mixed> $attributes the normalised, ordered attribute map
     */
    public static function named(string $name, array $attributes): self
    {
        return new self(self::key($name, $attributes), $attributes);
    }

    /**
     * The fresh series this slot's identity starts: the zeroed
     * counter and observation record carrying the slot's own
     * normalised attributes -- the series the first recording under
     * this identity accumulates onto.
     *
     * @return array{count:float|int,sum:float,attributes:array<string,mixed>}
     */
    public function freshSeries(): array
    {
        return ['count' => 0, 'sum' => 0.0, 'attributes' => $this->attributes];
    }

    /**
     * The composite key grammar: the name, the separator, the JSON
     * rendering of the attribute map.
     *
     * @param string              $name       the metric name
     * @param array<string,mixed> $attributes the normalised, ordered attribute map
     *
     * @throws JsonException when the attribute map cannot render -- the sanitised maps the meter reads never reach it
     */
    public static function key(string $name, array $attributes): string
    {
        return $name . '|' . json_encode(
            $attributes,
            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        );
    }
}
