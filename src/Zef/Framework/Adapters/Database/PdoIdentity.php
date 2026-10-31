<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;

/**
 * The last-insert identity's folding: the driver's raw answer (`''`,
 * `false` or `'0'` when no insert produced an identity) folded onto
 * the null the port promises.
 *
 * @internal
 */
final class PdoIdentity
{
    /**
     * The last auto-generated id, or null when the driver produced none.
     */
    public static function lastInsertId(PDO $pdo): ?string
    {
        $id = $pdo->lastInsertId();
        if (\in_array($id, ['', false, '0'], true)) {
            return null;
        }

        return $id;
    }
}
