<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP frame-await discipline: the walk every awaited answer
 * rides -- the heartbeat frames skipped (the protocol allows them
 * anytime, the negotiation asked for none), the connection close
 * the broker may spell mid-conversation translated into the port's
 * own refusal family with the broker's code and sentence carried
 * verbatim, a frame that rides a channel other than the one
 * awaited refused with both channels named, and a method the
 * caller never named refused with both methods spelled -- the one
 * place the wire's own judgments live, split from the connection
 * the same ceiling every slice's folds answer.
 */
final class AmqpFrameAwaiter
{
    public function __construct(
        private AmqpFrameReader $amqpFrameReader,
        private AmqpMethodDecoder $amqpMethodDecoder,
    ) {
    }

    /**
     * The next frame the broker carries for one channel.
     *
     * @throws InvalidArgumentException when the broker closed the
     *                                  connection, the frame rides the wrong channel, or the wire
     *                                  refuses
     */
    public function awaitFrame(int $channel, string $context): AmqpFrame
    {
        $amqpFrame = $this->amqpFrameReader->frame();

        if ($amqpFrame->type() === AmqpFrame::TYPE_HEARTBEAT) {
            return $this->awaitFrame($channel, $context);
        }

        if ($amqpFrame->channel() === 0) {
            if (
                $amqpFrame->type() === AmqpFrame::TYPE_METHOD
                && $amqpFrame->method()->equals(AmqpMethod::of('connection.close'))
            ) {
                $amqpServerClose = $this->amqpMethodDecoder->serverClose(
                    $amqpFrame->arguments(),
                );

                throw new InvalidArgumentException(
                    'The AMQP broker closed the connection while ' . $context
                    . ': ' . $amqpServerClose->replyCode() . ' '
                    . $amqpServerClose->replyText() . ' (the broker named class '
                    . $amqpServerClose->classId() . ' method '
                    . $amqpServerClose->methodId() . ').',
                );
            }

            if ($channel !== 0) {
                throw new InvalidArgumentException(
                    'The AMQP broker answered a frame on the connection\'s own'
                    . ' channel while ' . $context . '.',
                );
            }
        }

        if ($amqpFrame->channel() !== $channel) {
            throw new InvalidArgumentException(
                'The AMQP broker answered a frame on channel '
                . $amqpFrame->channel() . ' while channel ' . $channel
                . ' was awaited (' . $context . ').',
            );
        }

        return $amqpFrame;
    }

    /**
     * The next method frame the broker carries for one channel,
     * judged against the methods the caller awaited -- the broker's
     * own channel close translated into the port's refusal family
     * with its code and sentence carried verbatim, and a method the
     * caller never named refused with both methods spelled.
     *
     * @param AmqpMethod ...$expected the methods the caller awaited, one of which must answer
     *
     * @return array{AmqpMethod, string} the method that answered and its argument bytes
     *
     * @throws InvalidArgumentException when the broker closed the
     *                                  channel, answered an unexpected method, or the wire
     *                                  refuses
     */
    public function awaitMethod(int $channel, string $context, AmqpMethod ...$expected): array
    {
        $amqpFrame = $this->awaitFrame($channel, $context);

        if ($amqpFrame->type() !== AmqpFrame::TYPE_METHOD) {
            throw new InvalidArgumentException(
                'The AMQP broker answered a ' . $this->typeSpelling($amqpFrame->type())
                . ' frame while ' . $context . '.',
            );
        }

        $amqpMethod = $amqpFrame->method();

        foreach ($expected as $awaited) {
            if ($amqpMethod->equals($awaited)) {
                return [$amqpMethod, $amqpFrame->arguments()];
            }
        }

        if ($channel !== 0 && $amqpMethod->equals(AmqpMethod::of('channel.close'))) {
            $amqpServerClose = $this->amqpMethodDecoder->serverClose(
                $amqpFrame->arguments(),
            );

            throw new InvalidArgumentException(
                'The AMQP broker closed channel ' . $channel . ' while '
                . $context . ': ' . $amqpServerClose->replyCode() . ' '
                . $amqpServerClose->replyText() . ' (the broker named class '
                . $amqpServerClose->classId() . ' method '
                . $amqpServerClose->methodId() . ').',
            );
        }

        throw new InvalidArgumentException(
            'The AMQP broker answered ' . $amqpMethod->spelling() . ' while '
            . $context . '.',
        );
    }

    /**
     * A frame type's own spelling, for the refusals that name what
     * arrived instead of what was awaited.
     */
    private function typeSpelling(int $type): string
    {
        return match ($type) {
            AmqpFrame::TYPE_METHOD => 'method',
            AmqpFrame::TYPE_CONTENT_HEADER => 'content header',
            AmqpFrame::TYPE_CONTENT_BODY => 'content body',
            AmqpFrame::TYPE_HEARTBEAT => 'heartbeat',
            default => 'type ' . $type,
        };
    }
}
