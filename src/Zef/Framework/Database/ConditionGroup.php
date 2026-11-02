<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Override;

/**
 * The group composite: a parenthesised set of conditions joined by
 * one connector -- the nested-group form the reference carried as
 * the `whereNested` closure sugar, returned here as a VALUE: a group
 * is a condition like any leaf, composable inside other groups
 * without any imperative callback.
 *
 * The group owns the structural judgement the closure could not: a
 * group with no member is a state the construction itself refuses
 * (`()` is not a predicate), and the connector is spelled by the
 * named constructor -- `all()` joins with AND, `any()` with OR --
 * so no member ever needs to know which chain it belongs to.
 *
 * Rendering is deterministic: members render in construction order,
 * each member's bound values travelling after the fragment that
 * carries them, the group parenthesised as a whole so the outer
 * chain's connector can never reach inside.
 */
final readonly class ConditionGroup implements Condition
{
    /**
     * @param list<Condition> $members
     * @param 'AND'|'OR'      $connector
     */
    private function __construct(
        private array $members,
        private string $connector,
    ) {
    }

    /**
     * A group whose members join with AND.
     */
    public static function all(Condition ...$conditions): self
    {
        return new self(self::assertMembers('all()', array_values($conditions)), 'AND');
    }

    /**
     * A group whose members join with OR.
     */
    public static function any(Condition ...$conditions): self
    {
        return new self(self::assertMembers('any()', array_values($conditions)), 'OR');
    }

    #[Override]
    public function fragment(SqlIdentifier $sqlIdentifier): QueryFragment
    {
        $fragments = [];
        $params = [];

        foreach ($this->members as $member) {
            $queryFragment = $member->fragment($sqlIdentifier);
            $fragments[] = $queryFragment->fragment;
            $params = array_merge($params, $queryFragment->params);
        }

        return new QueryFragment('(' . implode(' ' . $this->connector . ' ', $fragments) . ')', $params);
    }

    /**
     * A group with no member is not a predicate; the refusal is the
     * construction's own, naming the constructor that received it.
     *
     * @param list<Condition> $conditions
     *
     * @return list<Condition>
     */
    private static function assertMembers(string $constructor, array $conditions): array
    {
        if ($conditions === []) {
            throw new QueryException("ConditionGroup::{$constructor} requires at least one condition.");
        }

        return $conditions;
    }
}
