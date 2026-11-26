<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP broker's own address: the hostname, the port and the
 * connect deadline, each validated once at construction -- the
 * refusal fold the socket's own corridor split out the same
 * ceiling every slice's folds answer. The address is the
 * operator's own spelling: a hostname an operator can name, a
 * port inside the engine's own bounds, a deadline strictly above
 * zero.
 */
final readonly class AmqpAddress
{
    /**
     * @throws InvalidArgumentException when the hostname, port or
     *                                  deadline refuses
     */
    public function __construct(
        private string $hostname,
        private int $port,
        private float $timeoutSeconds,
    ) {
        if ($this->hostname === '') {
            throw new InvalidArgumentException(
                'An AMQP connection must carry a non-empty hostname.',
            );
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw new InvalidArgumentException(
                'An AMQP connection port must be between 1 and 65535, got '
                . $this->port . '.',
            );
        }

        if ($this->timeoutSeconds <= 0.0) {
            throw new InvalidArgumentException(
                \sprintf(
                    'An AMQP connection timeout must be above zero, got %s.',
                    $this->timeoutSeconds,
                ),
            );
        }
    }

    /**
     * The broker's hostname.
     */
    public function hostname(): string
    {
        return $this->hostname;
    }

    /**
     * The broker's port.
     */
    public function port(): int
    {
        return $this->port;
    }

    /**
     * The connect and read deadline.
     */
    public function timeoutSeconds(): float
    {
        return $this->timeoutSeconds;
    }

    /**
     * The deadline's whole-second half -- the read-timeout
     * refinement's own first argument.
     */
    public function timeoutWholeSeconds(): int
    {
        return (int) floor($this->timeoutSeconds);
    }

    /**
     * The deadline's sub-second half, in microseconds -- the
     * read-timeout refinement's own second argument.
     */
    public function timeoutMicroseconds(): int
    {
        $wholeSeconds = floor($this->timeoutSeconds);

        return (int) round(($this->timeoutSeconds - $wholeSeconds) * 1_000_000.0);
    }
}
