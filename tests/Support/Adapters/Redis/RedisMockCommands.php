<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\Redis;

/**
 * The Redis mock server's command state machine: the eight commands
 * the delivery store's wire speaks plus the protocol's own health
 * verb, answered over real in-memory state -- strings with their PX
 * expiries checked lazily against the wall clock, sorted sets
 * carrying the (score, member) order the real server's index
 * answers. The reply is bytes: the mock speaks the grammar from the
 * server's own side, implemented independently of the client's
 * encoder and decoder on purpose -- an oracle, not a mirror, so a
 * grammar mistake on either side meets its contradiction instead of
 * its echo.
 *
 * @internal
 */
final class RedisMockCommands
{
    /**
     * The string keys the mock holds, each with its value and its
     * expiry in milliseconds since the epoch (null: no expiry).
     *
     * @var array<string, array{value: string, expiresAtMs: null|float}>
     */
    private array $strings = [];

    /**
     * The sorted sets the mock holds, each member with its score.
     *
     * @var array<string, array<string, float>>
     */
    private array $sortedSets = [];

    /**
     * Applies one command and answers its reply bytes.
     *
     * @param list<string> $arguments the verb first, its arguments after
     *
     * @return string the reply's own bytes
     */
    public function apply(array $arguments): string
    {
        if ($arguments === []) {
            return $this->error('ERR empty command');
        }

        return match (mb_strtoupper($arguments[0])) {
            'PING' => $this->simple('PONG'),
            'QUIT' => $this->simple('OK'),
            'SET' => $this->set($arguments),
            'GET' => $this->bulk($this->liveString($arguments[1] ?? '')),
            'MGET' => $this->mget($arguments),
            'DEL' => $this->integer($this->del($arguments)),
            'ZADD' => $this->zadd($arguments),
            'ZREM' => $this->zrem($arguments),
            'ZCARD' => $this->integer(\count($this->sortedSets[$arguments[1] ?? ''] ?? [])),
            'ZRANGEBYSCORE' => $this->zrangebyscore($arguments),
            default => $this->error("ERR unknown command '" . $arguments[0] . "'"),
        };
    }

    /**
     * SET with its NX and PX options: the plain write, the
     * only-if-absent claim, and the expiry the claim carries -- an
     * expired key treated as absent the way the real server's lazy
     * expiry treats it.
     *
     * @param list<string> $arguments
     */
    private function set(array $arguments): string
    {
        $key = $arguments[1] ?? '';
        $value = $arguments[2] ?? '';
        $onlyIfAbsent = false;
        $expiryMs = null;
        $counter = \count($arguments);

        for ($index = 3; $index < $counter; ++$index) {
            $option = mb_strtoupper($arguments[$index]);

            if ($option === 'NX') {
                $onlyIfAbsent = true;

                continue;
            }

            if ($option !== 'PX') {
                continue;
            }

            $expiryMs = (float) ($arguments[$index + 1] ?? '0');
            ++$index;
        }

        if ($onlyIfAbsent && $this->liveString($key) !== null) {
            return $this->bulk(null);
        }

        $this->strings[$key] = [
            'value' => $value,
            'expiresAtMs' => $expiryMs === null
                ? null
                : $this->nowMs() + $expiryMs,
        ];

        return $this->simple('OK');
    }

    /**
     * MGET: one element per key asked, the absent record the null
     * bulk.
     *
     * @param list<string> $arguments
     */
    private function mget(array $arguments): string
    {
        $values = [];
        $counter = \count($arguments);

        for ($index = 1; $index < $counter; ++$index) {
            $values[] = $this->liveString($arguments[$index]);
        }

        $elements = '';

        foreach ($values as $value) {
            $elements .= $this->bulk($value);
        }

        return '*' . \count($values) . "\r\n" . $elements;
    }

    /**
     * DEL: the count of live keys removed.
     *
     * @param list<string> $arguments
     */
    private function del(array $arguments): int
    {
        $removed = 0;
        $counter = \count($arguments);

        for ($index = 1; $index < $counter; ++$index) {
            $key = $arguments[$index];

            if ($this->liveString($key) === null) {
                continue;
            }

            unset($this->strings[$key]);
            ++$removed;
        }

        return $removed;
    }

    /**
     * ZADD: one member scored into its set, the reply counting the
     * members newly added.
     *
     * @param list<string> $arguments
     */
    private function zadd(array $arguments): string
    {
        $key = $arguments[1] ?? '';
        $score = (float) ($arguments[2] ?? '0');
        $member = $arguments[3] ?? '';
        $set = $this->sortedSets[$key] ?? [];
        $added = !\array_key_exists($member, $set);

        $set[$member] = $score;
        $this->sortedSets[$key] = $set;

        return $this->integer($added ? 1 : 0);
    }

    /**
     * ZREM: the count of members removed from their set.
     *
     * @param list<string> $arguments
     */
    private function zrem(array $arguments): string
    {
        $key = $arguments[1] ?? '';
        $removed = 0;
        $counter = \count($arguments);

        for ($index = 2; $index < $counter; ++$index) {
            $member = $arguments[$index];

            if (!\array_key_exists($member, $this->sortedSets[$key] ?? [])) {
                continue;
            }

            unset($this->sortedSets[$key][$member]);
            ++$removed;
        }

        return $this->integer($removed);
    }

    /**
     * ZRANGEBYSCORE: the members whose scores sit inside the
     * inclusive range, in the sorted set's own order -- score first,
     * member identity second, bytewise.
     *
     * @param list<string> $arguments
     */
    private function zrangebyscore(array $arguments): string
    {
        $key = $arguments[1] ?? '';
        $minimum = $this->boundOf($arguments[2] ?? '-inf');
        $maximum = $this->boundOf($arguments[3] ?? '+inf');
        $scored = [];

        foreach ($this->sortedSets[$key] ?? [] as $member => $score) {
            if ($score < $minimum || $score > $maximum) {
                continue;
            }

            $scored[] = [$score, $member];
        }

        usort(
            $scored,
            static function (array $left, array $right): int {
                $order = $left[0] <=> $right[0];

                return $order === 0 ? strcmp($left[1], $right[1]) : $order;
            },
        );

        $elements = '';

        foreach ($scored as [, $member]) {
            $elements .= $this->bulk($member);
        }

        return '*' . \count($scored) . "\r\n" . $elements;
    }

    /**
     * The live string at a key -- null when absent or expired, the
     * real server's own lazy expiry answered here.
     */
    private function liveString(string $key): ?string
    {
        $entry = $this->strings[$key] ?? null;

        if ($entry === null) {
            return null;
        }

        if ($entry['expiresAtMs'] !== null && $this->nowMs() > $entry['expiresAtMs']) {
            unset($this->strings[$key]);

            return null;
        }

        return $entry['value'];
    }

    /**
     * A range bound's own spelling: the infinities, or the number.
     */
    private function boundOf(string $spelling): float
    {
        if ($spelling === '-inf' || $spelling === '-infinity') {
            return -\INF;
        }

        if (\in_array($spelling, ['+inf', 'inf', '+infinity'], true)) {
            return \INF;
        }

        return (float) $spelling;
    }

    /**
     * The wall clock in milliseconds -- the expiry's own clock.
     */
    private function nowMs(): float
    {
        return microtime(true) * 1000.0;
    }

    /**
     * The simple string reply's bytes.
     */
    private function simple(string $value): string
    {
        return '+' . $value . "\r\n";
    }

    /**
     * The error reply's bytes.
     */
    private function error(string $message): string
    {
        return '-' . $message . "\r\n";
    }

    /**
     * The integer reply's bytes.
     */
    private function integer(int $value): string
    {
        return ':' . $value . "\r\n";
    }

    /**
     * The bulk reply's bytes -- the null spelling included.
     */
    private function bulk(?string $value): string
    {
        if ($value === null) {
            return "\$-1\r\n";
        }

        return '$' . mb_strlen($value, '8bit') . "\r\n" . $value . "\r\n";
    }
}
