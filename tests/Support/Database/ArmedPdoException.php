<?php

declare(strict_types=1);

namespace Zef\Test\Support\Database;

use PDOException;

/**
 * Armed PDO exception double: a PDOException whose SQLSTATE and driver
 * code slots the test spelled explicitly -- the shapes real driver
 * failures populate through PHP internals, which the public constructor
 * cannot reach (an anonymous subclass with a constructor argument is
 * the shape the style lanes refuse, so the double is named and lives
 * here beside the recording PDO).
 */
final class ArmedPdoException extends PDOException
{
    /**
     * @param int                           $code      the driver code getCode() answers
     * @param null|array<int|string, mixed> $errorInfo the driver's error-info slots, errorInfo verbatim
     */
    public function __construct(string $message, int $code, ?array $errorInfo = null)
    {
        parent::__construct($message, $code);
        if ($errorInfo === null) {
            return;
        }

        $this->errorInfo = $errorInfo;
    }
}
