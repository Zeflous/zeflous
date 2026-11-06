<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The type-mapping family's own refusal: a value that cannot cross the
 * statement boundary in the shape its column's map spells -- the
 * malformed JSON cell read back, the non-string cell a converter's read
 * direction does not accept, the wrong-side value type handed to a
 * write direction -- landing wherever a {@see ColumnType} converter's
 * own vocabulary halts. The same sub-family posture the query-log
 * family's corridor carries ({@see QueryLogException} over the
 * {@see DatabaseException} root): one typed halt the application can
 * catch, with the offending spelling named, never a silent null in
 * place of a value the map could not convert.
 */
final class TypeMappingException extends DatabaseException
{
}
