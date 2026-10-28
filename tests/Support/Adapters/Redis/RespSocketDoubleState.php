<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\Redis;

/**
 * The typed state the RESP socket boundary's function doubles answer
 * from: the armed connect answer (a loopback memory stream, or the
 * refused arm), the scripted read chunks (the reply bytes the wire
 * will receive, one chunk per engine read, a false chunk the dead
 * stream), the armed write answer (the refused arm or a partial
 * byte count the write loop must keep writing past), and the
 * observation ledgers -- every command's bytes the connection wrote,
 * and every read-deadline refinement it asked the stream for.
 *
 * Passthrough is the default for every answer, so every other suite
 * keeps the engine's real behaviour; only a test that arms an answer
 * sees a double's own.
 *
 * @internal
 */
final class RespSocketDoubleState
{
    /**
     * The connect answer: null the engine passthrough, false the
     * refused connection, a resource the armed stream the connection
     * will hold and read its scripted chunks through.
     *
     * @var null|false|resource
     */
    public static mixed $connectAnswer = null;

    /**
     * The errno the refused connect answers -- the engine's own
     * refusal vocabulary, armed beside the false connect answer so
     * the connect refusal names a real cause.
     */
    public static int $connectErrorCode = 111;

    /**
     * The error string the refused connect answers.
     */
    public static string $connectErrorString = 'Connection refused';

    /**
     * The scripted read chunks: null the engine passthrough, a list
     * (possibly empty -- an immediate end of stream) the armed reply
     * bytes. Each engine read pops one chunk, honours the requested
     * length, and leaves the remainder queued; a false chunk is the
     * dead-stream arm and persists; an exhausted queue answers the
     * empty string -- the stream's own end.
     *
     * @var null|list<false|string>
     */
    public static ?array $readChunks = null;

    /**
     * The write answer: null the engine passthrough, false the
     * refused write, an int the partial byte count the write loop
     * must keep writing past.
     */
    public static bool|int|null $writeAnswer = null;

    /**
     * Every command's bytes the connection wrote, in write order --
     * the wire's own observable, one entry per fwrite call.
     *
     * @var list<string>
     */
    public static array $writtenCommands = [];

    /**
     * Every read-deadline refinement the connection asked the stream
     * for, as the seconds/microseconds pairs it handed the engine.
     *
     * @var list<array{int, int}>
     */
    public static array $setTimeoutCalls = [];

    /**
     * Resets every armed answer and observation ledger to the
     * passthrough default -- the boundary between tests.
     */
    public static function reset(): void
    {
        self::$connectAnswer = null;
        self::$connectErrorCode = 111;
        self::$connectErrorString = 'Connection refused';
        self::$readChunks = null;
        self::$writeAnswer = null;
        self::$writtenCommands = [];
        self::$setTimeoutCalls = [];
    }
}
