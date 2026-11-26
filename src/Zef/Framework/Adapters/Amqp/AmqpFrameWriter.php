<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

/**
 * The AMQP frame writer: the one place a frame's own bytes are
 * spelled -- the seven header bytes (type, channel, size), the
 * payload, and the end byte the protocol demands after every frame
 * -- carried to the socket whole through the corridor's own
 * partial-write discipline. The segmentation a body larger than the
 * negotiated frame maximum demands is the content carriage's own
 * judgment, because only the channel knows the ceiling the
 * handshake negotiated.
 */
final class AmqpFrameWriter
{
    public function __construct(
        private AmqpSocket $amqpSocket,
    ) {
    }

    /**
     * Carries one frame.
     *
     * @param AmqpFrame $amqpFrame the frame to carry
     */
    public function frame(AmqpFrame $amqpFrame): void
    {
        $this->amqpSocket->write(
            pack(
                'CnN',
                $amqpFrame->type(),
                $amqpFrame->channel(),
                mb_strlen($amqpFrame->payload(), '8bit'),
            ) . $amqpFrame->payload() . "\xce",
        );
    }
}
