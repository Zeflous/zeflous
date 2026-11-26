<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

/**
 * The broker's own refusal: the connection.close or channel.close
 * the broker spells when it ends the conversation -- the reply code
 * (the AMQP reply-code vocabulary the refusal translation names),
 * the reply text (the broker's own sentence), and the class and
 * method the broker names as the refusal's subject. The family
 * never answers a close with a close-ok: the connection it owns is
 * the corridor it drops, and the broker's requeue of the held
 * deliveries is the crash-window answer the claim doctrine already
 * owns.
 */
final readonly class AmqpServerClose
{
    public function __construct(
        private int $replyCode,
        private string $replyText,
        private int $classId,
        private int $methodId,
    ) {
    }

    /**
     * The broker's own reply code -- the 2xx success, 3xx redirect,
     * 4xx content and 5xx execution families the protocol spells.
     */
    public function replyCode(): int
    {
        return $this->replyCode;
    }

    /**
     * The broker's own reply text -- the sentence the refusal
     * translation carries verbatim.
     */
    public function replyText(): string
    {
        return $this->replyText;
    }

    /**
     * The class the broker names as the refusal's subject.
     */
    public function classId(): int
    {
        return $this->classId;
    }

    /**
     * The method the broker names as the refusal's subject.
     */
    public function methodId(): int
    {
        return $this->methodId;
    }
}
