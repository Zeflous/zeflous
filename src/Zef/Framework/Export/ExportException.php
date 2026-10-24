<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use RuntimeException;

/**
 * The export family's fail-closed root: every defect in the inputs an
 * ecosystem document is rendered from -- an API identity that fails its
 * grammar, an enrichment declaration whose shape is wrong, a duplicate
 * enrichment the table spells twice -- refuses the whole export with
 * the offending route's own identity in the message, because an
 * integration artifact that silently guessed is worse than one that
 * refused.
 */
final class ExportException extends RuntimeException
{
}
