<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP 0-9-1 handshake fold: the conversation the wire opens
 * with -- the protocol header, the broker's introduction answered
 * with the PLAIN credentials, the negotiated ceilings answered with
 * the client's own, and the virtual host opened -- split from the
 * connection the same ceiling every slice's folds answer. The
 * negotiation is the client's own honest answer: the channel
 * ceiling the lower of the broker's and the client's own bound (the
 * broker's zero spells unlimited, answered with the client's own),
 * the frame ceiling the lower of the two (the same zero spelling),
 * and the heartbeat zero -- the family spells no heartbeat, because
 * a dead wire is the broker's own requeue signal. A broker whose
 * frame ceiling sits below the protocol's own floor is refused at
 * the handshake, the floor spelled.
 */
final class AmqpHandshake
{
    /** The client's own channel ceiling -- two channels is the store's whole need. */
    private const int CLIENT_CHANNEL_CEILING = 64;

    /** The client's own frame ceiling -- the broker's lower answer wins. */
    private const int CLIENT_FRAME_CEILING = 131072;

    /** The frame floor every peer of the protocol must accept. */
    private const int FRAME_FLOOR = 4096;

    /** The client properties the introduction spells. */
    private const array CLIENT_PROPERTIES = [
        'product' => 'ZEF Framework AMQP delivery store',
        'platform' => 'PHP',
    ];

    public function __construct(
        private AmqpSocket $amqpSocket,
        private AmqpFrameWriter $amqpFrameWriter,
        private AmqpFrameAwaiter $amqpFrameAwaiter,
        private AmqpMethodEncoder $amqpMethodEncoder,
    ) {
    }

    /**
     * Runs the handshake and answers the negotiated ceilings -- the
     * channel maximum and the frame maximum the connection rides.
     *
     * @return array{int, int<4096, max>} the negotiated channel and frame ceilings
     *
     * @throws InvalidArgumentException when the broker refuses any
     *                                  step of the handshake
     */
    public function run(string $username, string $password, string $virtualHost): array
    {
        $this->introduce($username, $password);
        $ceilings = $this->negotiate();
        $this->open($virtualHost);

        return $ceilings;
    }

    /**
     * The wire's own opening: the protocol header carried and the
     * broker's introduction answered with the PLAIN credentials.
     *
     * @throws InvalidArgumentException when the broker refuses the opening
     */
    private function introduce(string $username, string $password): void
    {
        $this->amqpSocket->write("AMQP\x00\x00\x09\x01");
        $this->amqpFrameAwaiter->awaitMethod(
            0,
            AmqpMethod::of('connection.start')->spelling() . ' was awaited',
            AmqpMethod::of('connection.start'),
        );

        $this->amqpFrameWriter->frame(
            new AmqpFrame(
                AmqpFrame::TYPE_METHOD,
                0,
                $this->amqpMethodEncoder->connectionStartOk(
                    self::CLIENT_PROPERTIES,
                    'PLAIN',
                    "\x00" . $username . "\x00" . $password,
                    'en_US',
                ),
            ),
        );
    }

    /**
     * The ceilings' own negotiation: the broker's tune answered with
     * the client's own bound, a broker below the protocol's own
     * floor refused with the floor spelled.
     *
     * @return array{int, int<4096, max>} the negotiated channel and frame ceilings
     *
     * @throws InvalidArgumentException when the broker refuses the negotiation
     */
    private function negotiate(): array
    {
        [, $tuneArguments] = $this->amqpFrameAwaiter->awaitMethod(
            0,
            AmqpMethod::of('connection.tune')->spelling() . ' was awaited',
            AmqpMethod::of('connection.tune'),
        );
        $amqpTune = (new AmqpMethodDecoder())->connectionTune($tuneArguments);
        $brokerFrameMax = $amqpTune->frameMax();

        if ($brokerFrameMax !== 0 && $brokerFrameMax < self::FRAME_FLOOR) {
            throw new InvalidArgumentException(
                'The AMQP broker\'s frame ceiling of ' . $brokerFrameMax
                . ' is below the protocol\'s own floor of ' . self::FRAME_FLOOR
                . ' -- the frames the wire spells could not ride it.',
            );
        }

        $channelMax = min(
            $amqpTune->channelMax() === 0
                ? self::CLIENT_CHANNEL_CEILING
                : $amqpTune->channelMax(),
            self::CLIENT_CHANNEL_CEILING,
        );
        $frameMax = max(
            self::FRAME_FLOOR,
            min(
                $brokerFrameMax === 0 ? self::CLIENT_FRAME_CEILING : $brokerFrameMax,
                self::CLIENT_FRAME_CEILING,
            ),
        );

        $this->amqpFrameWriter->frame(
            new AmqpFrame(
                AmqpFrame::TYPE_METHOD,
                0,
                $this->amqpMethodEncoder->connectionTuneOk($channelMax, $frameMax, 0),
            ),
        );

        return [$channelMax, $frameMax];
    }

    /**
     * The virtual host's own opening.
     *
     * @throws InvalidArgumentException when the broker refuses the virtual host
     */
    private function open(string $virtualHost): void
    {
        $this->amqpFrameWriter->frame(
            new AmqpFrame(
                AmqpFrame::TYPE_METHOD,
                0,
                $this->amqpMethodEncoder->connectionOpen($virtualHost),
            ),
        );
        $this->amqpFrameAwaiter->awaitMethod(
            0,
            AmqpMethod::of('connection.open-ok')->spelling() . ' was awaited',
            AmqpMethod::of('connection.open-ok'),
        );
    }
}
