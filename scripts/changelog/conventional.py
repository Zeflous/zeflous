#!/usr/bin/env python3
"""Conventional-Commit taxonomy -- the SINGLE SOURCE OF TRUTH.

WHY THIS MODULE EXISTS
----------------------
Two consumers need the exact same Conventional-Commit rules:

  * ``scripts/changelog/generate.py`` -- maps a PR title / commit subject onto a
    Release Drafter category (``feat:`` -> Features, ``fix:`` -> Bug Fixes, ...).
  * ``.github/workflows/pr-validator.yml`` -- the **PR Validator** gate, which
    fails a pull request whose title is not a valid Conventional-Commit header.

If each kept its own copy of the regex, the two would drift and a title could
pass the gate yet be categorised as "Other" (or vice versa). This module is the
one place the rules live; both import from here. There is no second copy.

WHAT A VALID TITLE LOOKS LIKE
-----------------------------
``type(scope)!: subject`` -- the scope and the breaking-change ``!`` are
optional, the type is one of ``ALLOWED_TYPES``::

    feat(api): add pagination
    fix: correct off-by-one
    docs(readme)!: rewrite the quickstart
    security: harden the token comparison

The type is matched case-insensitively (``Feat:`` is accepted) but the canonical
form is lower-case. A title that does not match is rejected by the gate.

RELATION TO RELEASE DRAFTER
---------------------------
``CONVENTIONAL_CATEGORY`` maps each type onto a category TITLE that is already
configured in ``.github/release-drafter.yml`` (the taxonomy's own source of
truth). A regex-derived category therefore can never conflict with a
label-derived one -- it can only ever select a bucket Release Drafter already
knows about. ``generate.py`` additionally checks the mapped title is present in
the config before using it, so a config change can never silently mis-bucket.

USAGE
-----
    python3 scripts/changelog/conventional.py --title "feat(api): add thing"
    # exit 0 -> valid; exit 1 -> invalid (message on stderr)

    from conventional import conventional_type, validate_title
"""

from __future__ import annotations

import argparse
import re
import sys

# Conventional-Commit type -> Release Drafter category TITLE.
#
# The titles on the right are exactly the ones configured in
# `.github/release-drafter.yml`, so a category derived from a title/commit regex
# can never conflict with one derived from a label. Types that Release Drafter
# folds into "Maintenance" (chore / refactor / perf / ci / build / style /
# revert) map there too, which is why the changelog shows the same buckets as
# the release notes.
CONVENTIONAL_CATEGORY: dict[str, str] = {
    "feat": "Features",
    "fix": "Bug Fixes",
    "docs": "Documentation",
    "chore": "Maintenance",
    "refactor": "Maintenance",
    "perf": "Maintenance",
    "test": "Tests",
    "ci": "Maintenance",
    "build": "Maintenance",
    "style": "Maintenance",
    "revert": "Maintenance",
    "security": "Security",
}

# The set of types the PR Validator accepts. Derived from the mapping above so
# the two can never disagree: a type is allowed iff it has a category.
ALLOWED_TYPES: tuple[str, ...] = tuple(CONVENTIONAL_CATEGORY)

# `type(scope)!: subject` -- the Conventional-Commit header. The scope and the
# breaking-change `!` are optional; only the leading type is captured.
CONVENTIONAL_RE = re.compile(r"^([a-z]+)(?:\([^)]*\))?!?:")

# A human-readable list of the accepted types, for the failure message.
ALLOWED_TYPES_HINT = ", ".join(ALLOWED_TYPES)


def conventional_type(text: str) -> str | None:
    """Return the Conventional-Commit type of a title/subject, or ``None``.

    ``"feat(api)!: add thing"`` -> ``"feat"``; ``"random title"`` -> ``None``.
    The match is case-insensitive; the returned type is lower-case.
    """
    match = CONVENTIONAL_RE.match((text or "").strip().lower())
    return match.group(1) if match else None


def is_conventional(text: str) -> bool:
    """True when ``text`` is a valid Conventional-Commit header.

    A header is valid when it starts with one of ``ALLOWED_TYPES`` followed by
    an optional ``(scope)``, an optional ``!`` and a ``:``. A recognised type
    that is not in ``ALLOWED_TYPES`` (e.g. ``wip:``) is NOT valid -- the gate
    only accepts the types the changelog can categorise.
    """
    ctype = conventional_type(text)
    return ctype is not None and ctype in CONVENTIONAL_CATEGORY


def validate_title(title: str) -> tuple[bool, str | None, str]:
    """Validate a PR title.

    Returns ``(ok, type, message)``:

    * ``ok``      -- whether the title is a valid Conventional-Commit header.
    * ``type``    -- the matched type (lower-case) when ``ok``, else ``None``.
    * ``message`` -- a human-readable explanation, suitable for a CI log.
    """
    stripped = (title or "").strip()
    if not stripped:
        return (
            False,
            None,
            "PR title is empty. A Conventional-Commit title is required, e.g. "
            "'feat(api): add pagination'.",
        )
    ctype = conventional_type(stripped)
    if ctype is None:
        return (
            False,
            None,
            f"PR title {stripped!r} is not a Conventional-Commit header. "
            f"Expected '<type>(<scope>): <subject>' with <type> one of: "
            f"{ALLOWED_TYPES_HINT}. Example: 'feat(api): add pagination'.",
        )
    if ctype not in CONVENTIONAL_CATEGORY:
        return (
            False,
            ctype,
            f"PR title type {ctype!r} is not allowed. Allowed types: "
            f"{ALLOWED_TYPES_HINT}. Example: 'fix(router): handle empty path'.",
        )
    return True, ctype, f"PR title is valid: type {ctype!r} -> category {CONVENTIONAL_CATEGORY[ctype]!r}."


def main(argv: list[str] | None = None) -> int:
    """CLI entry point used by the PR Validator workflow.

    Exit 0 when the title is valid, 1 when it is not (message on stderr), 2 on
    a usage error. The title is read from ``--title`` or the ``PR_TITLE``
    environment variable; the value is never echoed back verbatim beyond the
    validation message (a PR title is public, but keeping the surface small is
    good hygiene).
    """
    parser = argparse.ArgumentParser(
        description="Validate a pull-request title against the Conventional-Commit taxonomy.",
    )
    parser.add_argument(
        "--title",
        default=None,
        help="The PR title to validate. Defaults to the PR_TITLE environment variable.",
    )
    args = parser.parse_args(argv)

    import os

    title = args.title if args.title is not None else os.environ.get("PR_TITLE", "")
    ok, _ctype, message = validate_title(title)
    if ok:
        sys.stdout.write(f"OK: {message}\n")
        return 0
    sys.stderr.write(f"FAIL: {message}\n")
    return 1


if __name__ == "__main__":  # pragma: no cover - exercised via the CLI in CI
    raise SystemExit(main())
