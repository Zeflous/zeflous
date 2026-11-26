<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

/**
 * The AMQP method encoder: the client side of the method catalogue
 * -- every method the family sends, its argument list spelled from
 * the protocol's own field order over the wire writer's primitives.
 * The reserved fields the grammar still carries (the access tickets
 * every method rides after the class's own revolution retired them)
 * are spelled the way the broker demands: a zero ticket, an empty
 * reserved string, the false reserved bit. The server side lives in
 * {@see AmqpMethodDecoder}; the content frames a publish and a get
 * ride are the content carriage's own, never a method payload's.
 */
final class AmqpMethodEncoder
{
    /**
     * connection.start-ok: the client's own introduction -- its
     * properties table, the SASL mechanism the family speaks, the
     * mechanism's response (the PLAIN grammar's null-separated
     * username and password), and the locale.
     *
     * @param array<string, bool|int|string> $clientProperties the client's own properties
     * @param string                         $mechanism       the SASL mechanism, PLAIN
     * @param string                         $response        the mechanism's own response bytes
     * @param string                         $locale           the locale the client speaks
     */
    public function connectionStartOk(
        array $clientProperties,
        string $mechanism,
        string $response,
        string $locale,
    ): string {
        return $this->method(AmqpMethod::of('connection.start-ok'))
            ->raw((new AmqpFieldTable())->encode($clientProperties))
            ->shortstr($mechanism)
            ->longstr($response)
            ->shortstr($locale)
            ->bytes();
    }

    /**
     * connection.tune-ok: the client's own answer to the broker's
     * ceiling -- the negotiated channel maximum, frame maximum and
     * heartbeat the handshake's own fold computes.
     */
    public function connectionTuneOk(int $channelMax, int $frameMax, int $heartbeat): string
    {
        return $this->method(AmqpMethod::of('connection.tune-ok'))
            ->short($channelMax)
            ->long($frameMax)
            ->short($heartbeat)
            ->bytes();
    }

    /**
     * connection.open: the virtual host the connection rides.
     */
    public function connectionOpen(string $virtualHost): string
    {
        return $this->method(AmqpMethod::of('connection.open'))
            ->shortstr($virtualHost)
            ->shortstr('')
            ->raw((new AmqpBitField())->octet(false))
            ->bytes();
    }

    /**
     * channel.open: the channel's own opening -- the reserved
     * out-of-band flag the grammar carries, always false.
     */
    public function channelOpen(): string
    {
        return $this->method(AmqpMethod::of('channel.open'))
            ->shortstr('')
            ->bytes();
    }

    /**
     * queue.declare: one queue declared idempotently -- the passive
     * flag always false (the declare is the store's own ensures
     * posture, never an existence probe), the durability the
     * durable records demand, the exclusivity and auto-deletion the
     * standing lane refuses (a queue that dies with its connection
     * or its last interest is not a durable lane), the no-wait flag
     * always false (the declare's answer is the count the folds
     * read), and the empty argument table.
     */
    public function queueDeclare(string $queue, bool $durable): string
    {
        return $this->method(AmqpMethod::of('queue.declare'))
            ->short(0)
            ->shortstr($queue)
            ->raw((new AmqpBitField())->octet(false, $durable, false, false, false))
            ->raw((new AmqpFieldTable())->encode([]))
            ->bytes();
    }

    /**
     * basic.publish: one message's routing -- the exchange the
     * default exchange's empty spelling answers when the routing
     * key IS the queue name, the routing key, and the two delivery
     * flags (mandatory, immediate) the family never arms: a message
     * the broker cannot route is the broker's own refusal to spell,
     * never an unroutable message returned on a channel the family
     * does not read.
     */
    public function basicPublish(string $exchange, string $routingKey): string
    {
        return $this->method(AmqpMethod::of('basic.publish'))
            ->short(0)
            ->shortstr($exchange)
            ->shortstr($routingKey)
            ->raw((new AmqpBitField())->octet(false, false))
            ->bytes();
    }

    /**
     * basic.get: the pull the claim walk rides -- the no-ack flag
     * always false, because the unacked hold IS the claim.
     */
    public function basicGet(string $queue): string
    {
        return $this->method(AmqpMethod::of('basic.get'))
            ->short(0)
            ->shortstr($queue)
            ->raw((new AmqpBitField())->octet(false))
            ->bytes();
    }

    /**
     * basic.ack: one held delivery finished -- the multiple flag
     * always false, because the settle spells one entry at a time.
     */
    public function basicAck(int $deliveryTag): string
    {
        return $this->method(AmqpMethod::of('basic.ack'))
            ->longlong($deliveryTag)
            ->raw((new AmqpBitField())->octet(false))
            ->bytes();
    }

    /**
     * tx.select: the channel's own transactional posture -- every
     * publish after it buffers until the commit answers.
     */
    public function txSelect(): string
    {
        return $this->method(AmqpMethod::of('tx.select'))
            ->raw((new AmqpBitField())->octet(false))
            ->bytes();
    }

    /**
     * tx.commit: the buffered writes landed -- the broker's own
     * all-or-nothing answer the admission's whole-batch doctrine
     * rides.
     */
    public function txCommit(): string
    {
        return $this->method(AmqpMethod::of('tx.commit'))->bytes();
    }

    /**
     * One method's opening -- the class id and method id the wire
     * spells ahead of every argument list.
     */
    private function method(AmqpMethod $amqpMethod): AmqpWireWriter
    {
        return (new AmqpWireWriter())
            ->short($amqpMethod->classId())
            ->short($amqpMethod->methodId());
    }
}
