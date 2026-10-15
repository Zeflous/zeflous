<?php

declare(strict_types=1);

namespace Zef\Docs;

use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\NodeVisitorAbstract;

/**
 * Rewrites every PHP enum declaration into the final-class shape
 * doctum 5.6's NodeVisitor can digest.
 *
 * Doctum 5.6 (the newest release of code-lts/doctum) dispatches only
 * Class_, Interface_ and Trait_ AST nodes, so an enum never becomes a
 * reflection and its source file gets no generated page -- a hole the
 * documentation-drift gate then correctly reports. Until doctum grows
 * native enum support, this visitor rewrites each enum before doctum's
 * own visitor runs: the enum stays a documented type, its cases become
 * public constants carrying the backing value (the case's own name for
 * pure enums), its methods and constants stay as they are, and its
 * docblock travels with it. The implicit UnitEnum/BackedEnum
 * interfaces are dropped on purpose: doctum has no reflection for
 * them, and a dangling link is a worse page than a quieter one.
 *
 * Wired in `doctum.php` (the one place the documentation pipeline
 * reads); lives under `scripts/docs/` so the framework's own analysis
 * gates -- which audit `src`, `tests` and `benchmarks` only -- stay
 * untouched by tooling code.
 */
final class EnumAsClassRewriter extends NodeVisitorAbstract
{
    public function enterNode(Node $node): ?Node
    {
        if (!$node instanceof Stmt\Enum_) {
            return null;
        }

        return $this->asFinalClass($node);
    }

    /**
     * One enum's class shape: final, the rewritten statements, the
     * declaration's own comments -- nothing else travels. The
     * namespaced name is copied by hand because the NameResolver has
     * already visited the enum by the time this rewrite runs, and it
     * will not visit the replacement node on the same pass.
     */
    private function asFinalClass(Stmt\Enum_ $enum): Class_
    {
        $class = new Class_(
            $enum->name->name,
            [
                'flags' => Class_::MODIFIER_FINAL,
                'stmts' => $this->statements($enum),
            ],
            $enum->getAttributes(),
        );

        if ($enum->namespacedName !== null) {
            $class->namespacedName = new Name($enum->namespacedName->toString());
        }

        return $class;
    }

    /**
     * The enum body in class grammar: every case becomes a public
     * constant (backed value where there is one, the case's own name
     * where there is not); methods, constants and trait uses stay as
     * the enum declared them.
     *
     * @return list<Stmt>
     */
    private function statements(Stmt\Enum_ $enum): array
    {
        $statements = [];

        foreach ($enum->stmts as $statement) {
            if ($statement instanceof Stmt\EnumCase) {
                $statements[] = new ClassConst(
                    [new Const_($statement->name->name, $this->caseValue($statement))],
                    Class_::MODIFIER_PUBLIC,
                    $statement->getAttributes(),
                );

                continue;
            }

            $statements[] = $statement;
        }

        return $statements;
    }

    /**
     * One case's constant value: the backing value a backed enum
     * carries, or the case's own name for a pure enum.
     */
    private function caseValue(Stmt\EnumCase $case): Expr
    {
        if ($case->expr !== null) {
            return $case->expr;
        }

        return new String_($case->name->name, $case->getAttributes());
    }
}
