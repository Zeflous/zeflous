"""Unit tests for the shared Conventional-Commit taxonomy.

These live under ``scripts/changelog/`` (not ``tests/``) on purpose: the
repository's SonarCloud scope excludes ``scripts/**`` from analysis and
coverage, so the taxonomy's own tests stay out of the PHP project's quality
gate while still running in CI. They are executed by the ``test`` job of
``.github/workflows/changelog-tests.yml`` with a 90% coverage floor.

The module is loaded by path (like ``test_generate.py``) so the tests do not
depend on the working directory or on ``sys.path`` being pre-seeded.
"""

from __future__ import annotations

import importlib.util
from pathlib import Path

import pytest

REPO_ROOT = Path(__file__).resolve().parents[3]
MODULE_PATH = REPO_ROOT / "scripts" / "changelog" / "conventional.py"


def _load_module():
    spec = importlib.util.spec_from_file_location("changelog_conventional", MODULE_PATH)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


conv = _load_module()


# Every allowed type, with a representative valid title for each. This is the
# contract the PR Validator gate enforces.
VALID_TITLES = [
    ("feat", "feat(api): add pagination"),
    ("feat", "feat: add pagination"),
    ("feat", "feat(api)!: drop the legacy endpoint"),
    ("fix", "fix(router): handle an empty path"),
    ("fix", "fix: correct the off-by-one"),
    ("docs", "docs(readme): rewrite the quickstart"),
    ("chore", "chore(deps): bump phpstan"),
    ("refactor", "refactor(container): extract a factory"),
    ("perf", "perf(router): cache the compiled tree"),
    ("test", "test(router): cover the empty path"),
    ("ci", "ci(actions): pin the checkout action"),
    ("build", "build(composer): raise the PHP floor"),
    ("style", "style(src): normalise the imports"),
    ("revert", "revert: undo the router change"),
    ("security", "security(csrf): harden the token comparison"),
]

INVALID_TITLES = [
    "",
    "   ",
    "update stuff",
    "Update the readme",
    "wip: not a real type",
    "feature: wrong type name",
    "feat add pagination",  # missing the colon
    "feat(api) add pagination",  # missing the colon
    ": no type",
]


@pytest.mark.parametrize(("ctype", "title"), VALID_TITLES)
def test_valid_titles_pass(ctype: str, title: str) -> None:
    ok, matched, _msg = conv.validate_title(title)
    assert ok is True
    assert matched == ctype
    assert conv.is_conventional(title) is True
    assert conv.conventional_type(title) == ctype


@pytest.mark.parametrize("title", INVALID_TITLES)
def test_invalid_titles_fail(title: str) -> None:
    ok, _matched, msg = conv.validate_title(title)
    assert ok is False
    assert msg  # a human-readable reason is always produced
    assert conv.is_conventional(title) is False


def test_recognised_but_disallowed_type_reports_the_type() -> None:
    # A type that parses but is not in the taxonomy is rejected, and the
    # matched type is surfaced so the failure message can name it.
    ok, matched, _msg = conv.validate_title("wip: still working")
    assert ok is False
    assert matched == "wip"


def test_allowed_types_are_derived_from_the_mapping() -> None:
    # The allowed set and the category mapping can never disagree.
    assert set(conv.ALLOWED_TYPES) == set(conv.CONVENTIONAL_CATEGORY)
    assert "feat" in conv.ALLOWED_TYPES
    assert "security" in conv.ALLOWED_TYPES


def test_type_is_case_insensitive() -> None:
    ok, ctype, _msg = conv.validate_title("FEAT(api): shouty but valid")
    assert ok is True
    assert ctype == "feat"


def test_scope_and_breaking_marker_are_optional() -> None:
    assert conv.conventional_type("fix: x") == "fix"
    assert conv.conventional_type("fix(a/b): x") == "fix"
    assert conv.conventional_type("fix!: x") == "fix"
    assert conv.conventional_type("fix(a)!: x") == "fix"


def test_unknown_type_is_rejected_with_a_clear_message() -> None:
    ok, ctype, msg = conv.validate_title("wip: still working")
    assert ok is False
    assert ctype == "wip"  # the type was recognised, but it is not allowed
    assert "not allowed" in msg
    assert "feat" in msg  # the allowed list is shown


def test_non_conventional_title_message_lists_examples() -> None:
    ok, _ctype, msg = conv.validate_title("just some words")
    assert ok is False
    assert "Conventional-Commit" in msg
    assert "feat(api): add pagination" in msg


def test_empty_title_message() -> None:
    ok, _ctype, msg = conv.validate_title("")
    assert ok is False
    assert "empty" in msg


def test_cli_accepts_a_valid_title(capsys: pytest.CaptureFixture[str]) -> None:
    rc = conv.main(["--title", "feat(api): add pagination"])
    out = capsys.readouterr()
    assert rc == 0
    assert "OK" in out.out


def test_cli_rejects_an_invalid_title(capsys: pytest.CaptureFixture[str]) -> None:
    rc = conv.main(["--title", "update stuff"])
    out = capsys.readouterr()
    assert rc == 1
    assert "FAIL" in out.err


def test_cli_reads_the_title_from_the_environment(
    monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str]
) -> None:
    monkeypatch.setenv("PR_TITLE", "fix(router): handle an empty path")
    rc = conv.main([])
    out = capsys.readouterr()
    assert rc == 0
    assert "OK" in out.out


def test_cli_rejects_an_empty_environment_title(
    monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str]
) -> None:
    monkeypatch.delenv("PR_TITLE", raising=False)
    rc = conv.main([])
    out = capsys.readouterr()
    assert rc == 1
    assert "FAIL" in out.err
