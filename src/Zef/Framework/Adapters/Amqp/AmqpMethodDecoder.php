<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP method decoder: the server side of the method catalogue
 * -- every method the family reads, its argument list walked over
 * the wire reader's primitives with the method named on every
 * refusal, so a frame that ends before its grammar does is a broker
 * speaking past the protocol, never a value to guess at. The
 * methods the family reads but never decodes (connection.start's
 * own introduction, the reserved-string carriers the grammar keeps)
 * are deliberately absent: their bytes are the frame's own payload,
 * bounded by the frame size the reader already validated.
 */
final class AmqpMethodDecoder
{
    /**
     * connection.tune: the broker's three ceilings.
     *
     * @throws InvalidArgumentException when the arguments end before the grammar does
     */
    public function connectionTune(string $arguments): AmqpTune
    {
        return $this->decode(
            'connection.tune',
            $arguments,
            static function (AmqpWireReader $amqpWireReader): AmqpTune {
                return new AmqpTune(
                    $amqpWireReader->short(),
                    $amqpWireReader->long(),
                    $amqpWireReader->short(),
                );
            },
        );
    }

    /**
     * queue.declare-ok: the queue's own name and its two counts.
     *
     * @throws InvalidArgumentException when the arguments end before the grammar does
     */
    public function queueDeclareOk(string $arguments): AmqpDeclareOk
    {
        return $this->decode(
            'queue.declare-ok',
            $arguments,
            static function (AmqpWireReader $amqpWireReader): AmqpDeclareOk {
                return new AmqpDeclareOk(
                    $amqpWireReader->shortstr(),
                    $amqpWireReader->long(),
                    $amqpWireReader->long(),
                );
            },
        );
    }

    /**
     * basic.get-ok: the held delivery's own identity -- the tag, the
     * redelivered flag, the exchange and routing key it rode, and
     * the count behind it.
     *
     * @throws InvalidArgumentException when the arguments end before the grammar does
     */
    public function basicGetOk(string $arguments): AmqpGetOk
    {
        return $this->decode(
            'basic.get-ok',
            $arguments,
            static function (AmqpWireReader $amqpWireReader): AmqpGetOk {
                return new AmqpGetOk(
                    $amqpWireReader->longlong(),
                    $amqpWireReader->bit(),
                    $amqpWireReader->shortstr(),
                    $amqpWireReader->shortstr(),
                    $amqpWireReader->long(),
                );
            },
        );
    }

    /**
     * connection.close or channel.close: the broker's own refusal,
     * carried to the translation that names it in the port's own
     * family.
     *
     * @throws InvalidArgumentException when the arguments end before the grammar does
     */
    public function serverClose(string $arguments): AmqpServerClose
    {
        return $this->decode(
            'the broker close',
            $arguments,
            static function (AmqpWireReader $amqpWireReader): AmqpServerClose {
                return new AmqpServerClose(
                    $amqpWireReader->short(),
                    $amqpWireReader->shortstr(),
                    $amqpWireReader->short(),
                    $amqpWireReader->short(),
                );
            },
        );
    }

    /**
     * One method's own walk, wrapped so the refusal names the method
     * whose grammar the arguments refused -- the same wrapping
     * discipline the delivery records' decoder carries.
     *
     * @template T
     *
     * @param string                      $method    the method whose grammar is walked
     * @param string                      $arguments the method's own argument bytes
     * @param callable(AmqpWireReader): T $walk      the method's own field walk
     *
     * @return T
     *
     * @throws InvalidArgumentException when the arguments end before the grammar does
     */
    private function decode(string $method, string $arguments, callable $walk): mixed
    {
        try {
            return $walk(new AmqpWireReader($arguments));
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidArgumentException(
                'The ' . $method . ' arguments are refused: '
                . $invalidArgumentException->getMessage(),
                0,
                $invalidArgumentException,
            );
        }
    }
}
