<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Config\Config;
use Zef\Framework\Exception\InvariantViolationException;

/**
 * Dot-notation lookup versus plain nested array access: the price of the
 * repository's address language, measured on the frozen data both sides
 * share (see `ROADMAP.md`, "Configuration System + DSL + Radix Tree" --
 * the Performance rationale of the "makanan siap saji" contract).
 */
final class ConfigBench
{
    private const array DATA = [
        'database' => [
            'mysql' => ['host' => 'localhost', 'port' => 3306],
            'postgres' => ['host' => 'db.internal', 'port' => 5432],
        ],
        'debug' => true,
    ];

    private readonly Config $config;

    /** @var array{database: array{mysql: array{host: string, port: int}, postgres: array{host: string, port: int}}, debug: bool} */
    private array $plain = self::DATA;

    public function __construct()
    {
        $this->config = new Config(self::DATA);
    }

    #[Revs(10000)]
    public function benchDotNotationGet(): void
    {
        if ($this->config->get('database.mysql.host') !== 'localhost') {
            throw new InvariantViolationException('The dot-notation lookup must resolve.');
        }
    }

    #[Revs(10000)]
    public function benchDirectArrayAccess(): void
    {
        if ($this->plain['database']['mysql']['host'] !== 'localhost') {
            throw new InvariantViolationException('The direct array access must resolve.');
        }
    }
}
