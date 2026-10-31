<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

/**
 * Strictly validated connection configuration value object.
 *
 * Every field is checked at construction through its own reading fold
 * ({@see ConnectionFields}, {@see ConnectionHost} / {@see ConnectionPort},
 * {@see ConnectionOptions}) so misconfiguration surfaces with an exact
 * message instead of deep inside a driver connect call, and the DSN is
 * spelled by {@see ConnectionDsn}. Supported drivers: mysql, pgsql,
 * sqlite.
 *
 * The array-in shape is the configuration system's own map form: the
 * composition slice that wires the `database` family reads the frozen
 * knob block through this fold, and a host's explicit declaration is
 * the one-way precedence over every default spelled here.
 */
final readonly class ConnectionConfig
{
    /**
     * @param array{persistent?: bool, timeout?: float|int} $options
     */
    private function __construct(
        public string $driver,
        public ?string $host,
        public ?int $port,
        public string $dbname,
        public ?string $user,
        public ?string $password,
        public ?string $charset,
        public array $options,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $driver = ConnectionFields::readDriver($config);
        $dbname = ConnectionFields::readDbname($config, $driver);

        $host = null;
        $port = null;
        if ($driver !== 'sqlite') {
            $host = ConnectionHost::read($config, $driver);
            $port = ConnectionPort::read($config, $driver);
        }

        $user = ConnectionFields::readOptionalString($config, 'user', 'User');
        $password = ConnectionFields::readOptionalString($config, 'password', 'Password');
        $charset = ConnectionFields::readOptionalString($config, 'charset', 'Charset');
        if ($charset === null && $driver === 'mysql') {
            $charset = 'utf8mb4';
        }

        return new self(
            $driver,
            $host,
            $port,
            $dbname,
            $user,
            $password,
            $charset,
            ConnectionOptions::read($config['options'] ?? []),
        );
    }

    public function dsn(): string
    {
        return ConnectionDsn::of($this->driver, $this->host, $this->port, $this->dbname, $this->charset);
    }
}
