<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The family's generic table-data gateway: the constructible
 * spelling of the abstract {@see Repository} base -- what a host
 * composes when a table needs the base verbs and nothing of the
 * host's own.
 *
 * The base is abstract because every real gateway is the host's
 * own: their table, their finders, their composition. This class
 * is the one answer that needs none of that -- the generic
 * per-table gateway the reference's own demonstration spelled as
 * an anonymous extension, named here because the tree's style
 * keeps construction out of anonymous classes -- and it is the
 * answer {@see \Zef\Framework\DatabaseWiring::repository()} composes
 * when the host's service declaration names a table without a
 * gateway of its own. A host who later grows finders still extends
 * the base directly; the generic never stands in the way.
 *
 * The constructor is the base's own: the connection the gateway
 * travels over, and the table name validated eagerly ({@see
 * SqlIdentifier}'s grammar) so a typo surfaces at construction,
 * not at the first query.
 */
final class TableGateway extends Repository
{
}
