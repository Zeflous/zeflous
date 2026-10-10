"""Unit tests for the per-version changelog generator.

These live under ``scripts/changelog/`` (not ``tests/``) on purpose: the
repository's SonarCloud scope excludes ``scripts/**`` from analysis and
coverage, so the generator's own tests stay out of the PHP project's quality
gate while still running in CI. They are executed by the ``test`` job of
``.github/workflows/changelog-tests.yml`` with a 90% coverage floor.

The GitHub API is never called: the module's ``_api_get`` / ``_api_get_all``
seam is monkeypatched, so the tests are hermetic and deterministic.
"""

from __future__ import annotations

import importlib.util
import json
import sys
from pathlib import Path

import pytest

REPO_ROOT = Path(__file__).resolve().parents[3]
MODULE_PATH = REPO_ROOT / "scripts" / "changelog" / "generate.py"


def _load_module():
    spec = importlib.util.spec_from_file_location("changelog_generate", MODULE_PATH)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


gen = _load_module()

CATEGORIES = [
    {"title": "Features", "labels": ["feature", "enhancement"]},
    {"title": "Bug Fixes", "labels": ["fix", "bugfix", "bug"]},
    {"title": "Security", "labels": ["security"]},
    {"title": "Maintenance", "labels": ["chore", "maintenance", "dependencies", "ci"]},
    {"title": "Documentation", "labels": ["documentation", "docs"]},
    {"title": "Tests", "labels": ["test", "tests"]},
]

CONFIG_YAML = """\
categories:
  - title: "Features"
    labels: ["feature", "enhancement"]
  - title: "Bug Fixes"
    labels: ["fix", "bugfix", "bug"]
  - title: "Security"
    labels: ["security"]
  - title: "Maintenance"
    labels: ["chore", "maintenance", "dependencies", "ci"]
  - title: "Documentation"
    labels: ["documentation", "docs"]
  - title: "Tests"
    labels: ["test", "tests"]
"""


def _pr(number, title, merged_at, labels=None, author="alice", base="main", draft=False, archived=False):
    return {
        "number": number,
        "title": title,
        "merged_at": merged_at,
        "draft": draft,
        "archived": archived,
        "base": {"ref": base},
        "labels": [{"name": x} for x in (labels or [])],
        "user": {"login": author},
        "html_url": f"https://github.com/o/r/pull/{number}",
    }


def _commit(sha, message):
    return {"sha": sha, "commit": {"message": message}}


def _release(tag, published_at, draft=False):
    return {"tag_name": tag, "name": tag, "draft": draft, "published_at": published_at}


# --------------------------------------------------------------------------
# Pure helpers
# --------------------------------------------------------------------------


def test_version_filename_unreleased():
    assert gen.version_filename("Unreleased") == "CHANGELOG-unreleased.md"


def test_version_filename_semver():
    assert gen.version_filename("v1.2.3") == "CHANGELOG-v1.2.3.md"


def test_version_filename_sanitises_unsafe_characters():
    assert gen.version_filename("v1.0.0/../evil") == "CHANGELOG-v1.0.0-..-evil.md"


def test_semver_key_orders_numerically():
    assert gen._semver_key("v1.10.0") > gen._semver_key("v1.9.0")
    assert gen._semver_key("not-a-version") == (0, 0, 0)


# --------------------------------------------------------------------------
# Conventional-Commit regex -> category
# --------------------------------------------------------------------------


def test_conventional_type_plain():
    assert gen.conventional_type("feat: add thing") == "feat"


def test_conventional_type_with_scope():
    assert gen.conventional_type("fix(api): repair thing") == "fix"


def test_conventional_type_with_breaking_bang():
    assert gen.conventional_type("feat(api)!: break thing") == "feat"


def test_conventional_type_is_case_insensitive():
    assert gen.conventional_type("FIX: shout") == "fix"


def test_conventional_type_none_for_plain_title():
    assert gen.conventional_type("random title") is None


def test_conventional_type_none_for_empty():
    assert gen.conventional_type("") is None


@pytest.mark.parametrize(
    ("title", "expected"),
    [
        ("feat(x): a", "Features"),
        ("fix(x): a", "Bug Fixes"),
        ("docs(x): a", "Documentation"),
        ("chore(x): a", "Maintenance"),
        ("refactor(x): a", "Maintenance"),
        ("perf(x): a", "Maintenance"),
        ("test(x): a", "Tests"),
        ("ci(x): a", "Maintenance"),
        ("build(x): a", "Maintenance"),
        ("style(x): a", "Maintenance"),
        ("revert(x): a", "Maintenance"),
        ("security(x): a", "Security"),
    ],
)
def test_category_for_regex_maps_every_conventional_type(title, expected):
    """Every Conventional-Commit type maps onto a configured category title."""
    assert gen.category_for([], title, CATEGORIES) == expected


def test_category_for_label_wins_over_regex():
    """A configured label is authoritative; the regex is only a fallback."""
    assert gen.category_for(["fix"], "feat: misleading title", CATEGORIES) == "Bug Fixes"


def test_category_for_is_case_insensitive():
    assert gen.category_for(["FIX"], "anything", CATEGORIES) == "Bug Fixes"


def test_category_for_falls_back_to_commit_subject():
    """A non-conventional title still categorises from its commits."""
    assert (
        gen.category_for([], "Tidy up", CATEGORIES, ["feat: real change"])
        == "Features"
    )


def test_category_for_unknown_is_other():
    assert gen.category_for([], "random title", CATEGORIES) == "Other"


def test_category_for_unknown_prefix_is_other():
    assert gen.category_for([], "wibble: nope", CATEGORIES) == "Other"


def test_category_for_regex_only_maps_to_configured_titles():
    """A type whose target title is not configured must not invent a bucket."""
    only_features = [{"title": "Features", "labels": ["feature"]}]
    assert gen.category_for([], "security: x", only_features) == "Other"


# --------------------------------------------------------------------------
# API-backed fetchers (monkeypatched seam)
# --------------------------------------------------------------------------


def test_fetch_pr_commits_dedupes_by_sha(monkeypatch):
    sha = "a" * 40
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [
            _commit(sha, "feat: one\n\nbody"),
            _commit(sha, "feat: one\n\nbody"),
            _commit("b" * 40, "fix: two"),
        ],
    )
    commits = gen.fetch_pr_commits("o/r", 1)
    assert [c["short_sha"] for c in commits] == ["a" * 7, "b" * 7]
    assert commits[0]["subject"] == "feat: one"


def test_fetch_pr_commits_handles_api_error(monkeypatch):
    import urllib.error

    def boom(path):
        raise urllib.error.HTTPError(path, 404, "nope", {}, None)

    monkeypatch.setattr(gen, "_api_get_all", boom)
    assert gen.fetch_pr_commits("o/r", 1) == []


def test_fetch_merged_prs_excludes_changelog_automation(monkeypatch):
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [
            _pr(1, "feat: real", "2026-01-01T00:00:00Z"),
            _pr(2, "chore(changelog): regenerate API changelog", "2026-01-02T00:00:00Z"),
            _pr(3, "not merged", None),
        ],
    )
    merged = gen.fetch_merged_prs("o/r")
    assert [p["number"] for p in merged] == [1]


def test_fetch_merged_prs_excludes_official_changelog_automation(monkeypatch):
    """The OFFICIAL changelog lane's bookkeeping PRs must be excluded too.

    Regression guard for the reported leak: the exclusion used to match only
    ``chore(changelog):``, so the official lane's ``chore(changelog-official):``
    PRs leaked into the per-version changelog (66 lines of self-description on
    ``main``). Both automation prefixes must be filtered.
    """
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [
            _pr(1, "feat: real", "2026-01-01T00:00:00Z"),
            _pr(2, "chore(changelog): regenerate API changelog", "2026-01-02T00:00:00Z"),
            _pr(3, "chore(changelog-official): regenerate official changelog", "2026-01-03T00:00:00Z"),
        ],
    )
    merged = gen.fetch_merged_prs("o/r")
    assert [p["number"] for p in merged] == [1]


def test_changelog_pr_prefixes_cover_both_lanes():
    """Both automation lanes must be represented in the exclusion tuple."""
    assert "chore(changelog):" in gen.CHANGELOG_PR_PREFIXES
    assert "chore(changelog-official):" in gen.CHANGELOG_PR_PREFIXES


def test_official_workflow_pattern_covers_both_automation_lanes():
    """F-4: the workflow's automation-title pattern must cover BOTH lanes.

    The official lane filters its snapshot with a regex held in
    ``.github/workflows/changelog-official.yml`` (``AUTOMATION_TITLE_PATTERN``).
    That regex and ``CHANGELOG_PR_PREFIXES`` describe the same two automation
    lanes; this test fails if the workflow pattern stops matching either lane's
    PR title, so the two sides of the contract cannot drift silently.
    """
    import re

    workflow = (
        REPO_ROOT / ".github" / "workflows" / "changelog-official.yml"
    ).read_text(encoding="utf-8")
    match = re.search(r"AUTOMATION_TITLE_PATTERN:\s*'([^']+)'", workflow)
    assert match, "AUTOMATION_TITLE_PATTERN not found in changelog-official.yml"
    pattern = re.compile(match.group(1))
    assert pattern.search("chore(changelog): regenerate API changelog")
    assert pattern.search("chore(changelog-official): regenerate official changelog")


def test_fetch_merged_prs_excludes_closed_without_merge(monkeypatch):
    """A PR closed WITHOUT being merged must never enter the changelog.

    This is the regression guard for the reported bug: selecting on
    ``state == "closed"`` alone would include a closed-unmerged PR, which never
    changed the default branch.
    """
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [
            _pr(1, "feat: merged", "2026-01-01T00:00:00Z"),
            _pr(2, "feat: abandoned", None),  # closed, never merged
        ],
    )
    merged = gen.fetch_merged_prs("o/r")
    assert [p["number"] for p in merged] == [1]


def test_fetch_merged_prs_excludes_draft(monkeypatch):
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [
            _pr(1, "feat: merged", "2026-01-01T00:00:00Z"),
            _pr(2, "feat: draft", "2026-01-02T00:00:00Z", draft=True),
        ],
    )
    merged = gen.fetch_merged_prs("o/r")
    assert [p["number"] for p in merged] == [1]


def test_fetch_merged_prs_excludes_archived(monkeypatch):
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [
            _pr(1, "feat: merged", "2026-01-01T00:00:00Z"),
            _pr(2, "feat: archived", "2026-01-02T00:00:00Z", archived=True),
        ],
    )
    merged = gen.fetch_merged_prs("o/r")
    assert [p["number"] for p in merged] == [1]


def test_fetch_merged_prs_excludes_non_default_branch(monkeypatch):
    """A PR merged into a branch other than the default never reached main."""
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [
            _pr(1, "feat: to main", "2026-01-01T00:00:00Z", base="main"),
            _pr(2, "feat: to release", "2026-01-02T00:00:00Z", base="release/1.x"),
        ],
    )
    merged = gen.fetch_merged_prs("o/r")
    assert [p["number"] for p in merged] == [1]


def test_fetch_merged_prs_keeps_only_merged_and_sorts(monkeypatch):
    """End-to-end selection: only merged-to-main PRs, oldest first."""
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [
            _pr(3, "feat: later", "2026-03-01T00:00:00Z"),
            _pr(1, "feat: earlier", "2026-01-01T00:00:00Z"),
            _pr(2, "feat: abandoned", None),
            _pr(4, "feat: draft", "2026-04-01T00:00:00Z", draft=True),
            _pr(5, "feat: other branch", "2026-05-01T00:00:00Z", base="dev"),
        ],
    )
    merged = gen.fetch_merged_prs("o/r")
    assert [p["number"] for p in merged] == [1, 3]


def test_fetch_tags_sorted_by_semver(monkeypatch):
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [
            {"name": "v1.10.0", "commit": {"sha": "s1"}},
            {"name": "v1.9.0", "commit": {"sha": "s2"}},
        ],
    )
    monkeypatch.setattr(
        gen,
        "_api_get",
        lambda path: {"commit": {"committer": {"date": "2026-01-01T00:00:00Z"}}},
    )
    tags = gen.fetch_tags("o/r")
    assert [t["name"] for t in tags] == ["v1.9.0", "v1.10.0"]


# --------------------------------------------------------------------------
# Releases (the version source of truth)
# --------------------------------------------------------------------------


def test_fetch_releases_excludes_draft_and_sorts(monkeypatch):
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [
            _release("v1.10.0", "2026-02-01T00:00:00Z"),
            _release("v1.9.0", "2026-01-01T00:00:00Z"),
            _release("v2.0.0", None, draft=True),
        ],
    )
    releases = gen.fetch_releases("o/r")
    assert [r["name"] for r in releases] == ["v1.9.0", "v1.10.0"]


def test_fetch_releases_skips_nameless(monkeypatch):
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [{"tag_name": "", "name": "", "draft": False, "published_at": None}],
    )
    assert gen.fetch_releases("o/r") == []


def test_fetch_next_release_returns_newest_draft(monkeypatch):
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [
            _release("v1.0.0", "2026-01-01T00:00:00Z"),
            _release("v1.1.0", None, draft=True),
            _release("v1.2.0", None, draft=True),
        ],
    )
    assert gen.fetch_next_release("o/r") == "v1.2.0"


def test_fetch_next_release_none_when_no_draft(monkeypatch):
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [_release("v1.0.0", "2026-01-01T00:00:00Z")],
    )
    assert gen.fetch_next_release("o/r") is None


def test_version_markers_prefers_releases(monkeypatch):
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: (
            [_release("v1.0.0", "2026-01-01T00:00:00Z")]
            if "/releases" in path
            else [{"name": "v9.9.9", "commit": {"sha": "s"}}]
        ),
    )
    monkeypatch.setattr(
        gen, "_api_get", lambda path: {"commit": {"committer": {"date": "2026-01-01T00:00:00Z"}}}
    )
    markers, source = gen._version_markers("o/r")
    assert source == "github_release"
    assert [m["name"] for m in markers] == ["v1.0.0"]


def test_version_markers_falls_back_to_tags(monkeypatch):
    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: (
            []
            if "/releases" in path
            else [{"name": "v1.0.0", "commit": {"sha": "s"}}]
        ),
    )
    monkeypatch.setattr(
        gen, "_api_get", lambda path: {"commit": {"committer": {"date": "2026-01-01T00:00:00Z"}}}
    )
    markers, source = gen._version_markers("o/r")
    assert source == "git_tag"
    assert [m["name"] for m in markers] == ["v1.0.0"]


def test_version_markers_none(monkeypatch):
    monkeypatch.setattr(gen, "_api_get_all", lambda path: [])
    markers, source = gen._version_markers("o/r")
    assert markers == []
    assert source == "none"


# --------------------------------------------------------------------------
# Model building
# --------------------------------------------------------------------------


def _patch_api(monkeypatch, tags, prs, commits_by_pr, releases=None):
    def fake_get_all(path):
        if "/releases" in path:
            return releases or []
        if "/tags" in path:
            return tags
        if "/pulls?" in path:
            return prs
        for number, commits in commits_by_pr.items():
            if f"/pulls/{number}/commits" in path:
                return commits
        return []

    monkeypatch.setattr(gen, "_api_get_all", fake_get_all)
    monkeypatch.setattr(
        gen,
        "_api_get",
        lambda path: {"commit": {"committer": {"date": "2026-01-01T00:00:00Z"}}},
    )


def test_build_model_groups_by_version_and_attaches_commits(monkeypatch):
    tags = [{"name": "v1.0.0", "commit": {"sha": "t"}}]
    prs = [
        _pr(1, "feat: before tag", "2025-12-31T00:00:00Z", ["feature"]),
        _pr(2, "fix: after tag", "2026-02-01T00:00:00Z", ["fix"]),
    ]
    commits = {
        1: [_commit("a" * 40, "feat: before tag")],
        2: [_commit("b" * 40, "fix: after tag")],
    }
    _patch_api(monkeypatch, tags, prs, commits)
    model = gen.build_model("o/r", {"categories": CATEGORIES})

    versions = {v["version"]: v for v in model["versions"]}
    assert set(versions) == {"Unreleased", "v1.0.0"}
    # PR #1 merged before the tag -> v1.0.0; PR #2 after -> Unreleased.
    assert [e["pr"] for e in versions["v1.0.0"]["entries"]] == [1]
    assert [e["pr"] for e in versions["Unreleased"]["entries"]] == [2]
    assert versions["v1.0.0"]["file"] == "CHANGELOG-v1.0.0.md"
    assert versions["Unreleased"]["file"] == "CHANGELOG-unreleased.md"
    assert versions["Unreleased"]["entries"][0]["commits"][0]["short_sha"] == "b" * 7
    assert model["unreleased_count"] == 1
    assert model["latest_release"] == "v1.0.0"
    assert model["as_of"] == "2026-02-01T00:00:00Z"
    # No published releases -> the tag fallback is the version source.
    assert model["version_source"] == "git_tag"


def test_build_model_uses_release_versions(monkeypatch):
    """Version buckets come from RELEASES, not tags, when releases exist."""
    releases = [
        _release("v1.0.0", "2026-01-15T00:00:00Z"),
        _release("v2.0.0", None, draft=True),
    ]
    prs = [
        _pr(1, "feat: before release", "2026-01-10T00:00:00Z", ["feature"]),
        _pr(2, "fix: after release", "2026-02-01T00:00:00Z", ["fix"]),
    ]
    _patch_api(monkeypatch, [], prs, {1: [], 2: []}, releases=releases)
    model = gen.build_model("o/r", {"categories": CATEGORIES})

    versions = {v["version"]: v for v in model["versions"]}
    assert set(versions) == {"Unreleased", "v1.0.0"}
    assert [e["pr"] for e in versions["v1.0.0"]["entries"]] == [1]
    assert [e["pr"] for e in versions["Unreleased"]["entries"]] == [2]
    assert model["version_source"] == "github_release"
    assert model["latest_release"] == "v1.0.0"
    # The draft release is surfaced as the next-version hint, not as a bucket.
    assert model["next_release"] == "v2.0.0"
    assert model["schema_version"] == 3


def test_build_model_dedupes_pr_number(monkeypatch):
    prs = [
        _pr(1, "feat: one", "2026-01-01T00:00:00Z", ["feature"]),
        _pr(1, "feat: one", "2026-01-01T00:00:00Z", ["feature"]),
    ]
    _patch_api(monkeypatch, [], prs, {1: []})
    model = gen.build_model("o/r", {"categories": CATEGORIES})
    assert len(model["versions"][0]["entries"]) == 1


def test_build_model_excludes_closed_unmerged_and_draft(monkeypatch):
    """The model must contain only merged-to-main PRs, never closed/draft ones."""
    prs = [
        _pr(1, "feat: merged", "2026-01-01T00:00:00Z", ["feature"]),
        _pr(2, "feat: abandoned", None, ["feature"]),  # closed, never merged
        _pr(3, "feat: draft", "2026-01-02T00:00:00Z", ["feature"], draft=True),
        _pr(4, "feat: other branch", "2026-01-03T00:00:00Z", ["feature"], base="dev"),
    ]
    _patch_api(monkeypatch, [], prs, {1: [], 2: [], 3: [], 4: []})
    model = gen.build_model("o/r", {"categories": CATEGORIES})
    numbers = [e["pr"] for v in model["versions"] for e in v["entries"]]
    assert numbers == [1]


def test_build_model_no_tags_is_all_unreleased(monkeypatch):
    prs = [_pr(1, "feat: one", "2026-01-01T00:00:00Z", ["feature"])]
    _patch_api(monkeypatch, [], prs, {1: []})
    model = gen.build_model("o/r", {"categories": CATEGORIES})
    assert [v["version"] for v in model["versions"]] == ["Unreleased"]
    assert model["latest_release"] is None
    assert model["version_source"] == "none"


def test_build_model_categorises_from_commit_subject(monkeypatch):
    """A non-conventional PR title still categorises from its commits."""
    prs = [_pr(1, "Tidy up the thing", "2026-01-01T00:00:00Z")]
    _patch_api(monkeypatch, [], prs, {1: [_commit("a" * 40, "feat: real change")]})
    model = gen.build_model("o/r", {"categories": CATEGORIES})
    assert model["versions"][0]["entries"][0]["category"] == "Features"


# --------------------------------------------------------------------------
# Rendering
# --------------------------------------------------------------------------


def _model_with_one_pr():
    return {
        "schema_version": 3,
        "as_of": "2026-01-01T00:00:00Z",
        "repository": "o/r",
        "version_source": "github_release",
        "latest_release": None,
        "next_release": "v0.1.0",
        "unreleased_count": 1,
        "versions": [
            {
                "version": "Unreleased",
                "date": None,
                "file": "CHANGELOG-unreleased.md",
                "categories": {"Features": []},
                "entries": [
                    {
                        "pr": 1,
                        "title": "feat: one",
                        "author": "alice",
                        "category": "Features",
                        "merged_at": "2026-01-01T00:00:00Z",
                        "url": "https://github.com/o/r/pull/1",
                        "file": "CHANGELOG-unreleased.md",
                        "commits": [
                            {"sha": "a" * 40, "short_sha": "a" * 7, "subject": "feat: one"}
                        ],
                    }
                ],
            }
        ],
    }


def test_render_version_markdown_has_reference_style_pr_line():
    md = gen.render_version_markdown(_model_with_one_pr()["versions"][0], repo="o/r")
    assert md.startswith("# Changelog")
    assert "## Unreleased" in md
    # Reference-style PR line: title + [#n](url) + ([author](author-url)).
    assert (
        "- feat: one [#1](https://github.com/o/r/pull/1) "
        "([alice](https://github.com/alice))" in md
    )
    # Commits stay beneath the PR so the markdown agrees with the JSON API.
    assert f"    - `{'a' * 7}` feat: one" in md
    # The generated-file banner is retained.
    assert "<!-- GENERATED FILE - do not edit by hand. -->" in md


def test_render_version_markdown_groups_entries_by_category():
    model = _model_with_one_pr()
    version = model["versions"][0]
    version["categories"] = {"Features": [], "Bug Fixes": []}
    version["entries"].append(
        {
            **version["entries"][0],
            "pr": 2,
            "title": "fix: two",
            "category": "Bug Fixes",
            "url": "https://github.com/o/r/pull/2",
            "commits": [],
        }
    )
    md = gen.render_version_markdown(version, repo="o/r")
    assert "**Features:**" in md
    assert "**Bug Fixes:**" in md
    assert md.index("**Features:**") < md.index("**Bug Fixes:**")


def test_render_version_markdown_infers_repo_from_entry_url():
    """Called without ``repo``, the slug is derived from an entry's PR URL."""
    md = gen.render_version_markdown(_model_with_one_pr()["versions"][0])
    assert "github.com/o/r" in md


def test_render_version_markdown_entry_without_url_or_author():
    version = _model_with_one_pr()["versions"][0]
    entry = version["entries"][0]
    entry["url"] = ""
    entry["author"] = ""
    md = gen.render_version_markdown(version, repo="o/r")
    assert "- feat: one #1 ([unknown](https://github.com/unknown))" in md


def test_repo_slug_prefers_explicit_repo_then_entry_url():
    version = _model_with_one_pr()["versions"][0]
    assert gen._repo_slug("x/y", version) == "x/y"
    assert gen._repo_slug(None, version) == "o/r"
    assert gen._repo_slug(None, {"entries": []}) == gen.DEFAULT_REPO


def test_render_version_markdown_without_commits():
    version = _model_with_one_pr()["versions"][0]
    version["entries"][0]["commits"] = []
    md = gen.render_version_markdown(version)
    assert "(no commits recorded)" in md


def test_render_version_markdown_release_heading_and_compare_link():
    model = _model_with_one_pr()
    version = model["versions"][0]
    version["version"] = "v1.2.3"
    version["file"] = "CHANGELOG-v1.2.3.md"
    version["date"] = "2026-03-04T00:00:00Z"
    md = gen.render_version_markdown(version, repo="o/r", previous="v1.2.2")
    assert "## [v1.2.3](https://github.com/o/r/tree/v1.2.3) (2026-03-04)" in md
    assert "[Full Changelog](https://github.com/o/r/compare/v1.2.2...v1.2.3)" in md


def test_render_version_markdown_unreleased_compares_to_head():
    version = _model_with_one_pr()["versions"][0]
    md = gen.render_version_markdown(version, repo="o/r", previous="v1.0.0")
    assert "## Unreleased" in md
    assert "[Full Changelog](https://github.com/o/r/compare/v1.0.0...HEAD)" in md


def test_render_version_markdown_without_previous_has_no_compare_link():
    version = _model_with_one_pr()["versions"][0]
    md = gen.render_version_markdown(version, repo="o/r")
    assert "Full Changelog" not in md


def test_render_index_markdown_with_release_date():
    model = _model_with_one_pr()
    model["versions"][0]["date"] = "2026-03-04T00:00:00Z"
    md = gen.render_index_markdown(model)
    assert "| 2026-03-04 |" in md


def test_render_version_markdown_empty_version():
    version = _model_with_one_pr()["versions"][0]
    version["entries"] = []
    md = gen.render_version_markdown(version)
    assert "Tidak ada perubahan." in md


def test_render_index_markdown_links_each_version():
    md = gen.render_index_markdown(_model_with_one_pr())
    assert "| Unreleased |" in md
    assert "[CHANGELOG-unreleased.md](CHANGELOG-unreleased.md)" in md


def test_render_index_json_is_small_and_has_file():
    data = json.loads(gen.render_index_json(_model_with_one_pr()))
    assert data["versions"][0]["file"] == "CHANGELOG-unreleased.md"
    assert data["versions"][0]["pr_count"] == 1
    assert "entries" not in data["versions"][0]


def test_render_index_json_has_version_source_and_next_release():
    data = json.loads(gen.render_index_json(_model_with_one_pr()))
    assert data["version_source"] == "github_release"
    assert data["next_release"] == "v0.1.0"


def test_render_json_roundtrips():
    model = _model_with_one_pr()
    assert json.loads(gen.render_json(model)) == model


def test_all_outputs_links_each_release_to_its_predecessor():
    """`_all_outputs` wires each version's `[Full Changelog]` to the older one."""
    model = _model_with_one_pr()
    model["versions"] = [
        {"version": "Unreleased", "date": None, "file": "CHANGELOG-unreleased.md", "categories": {}, "entries": []},
        {"version": "v2.0.0", "date": "2026-02-01T00:00:00Z", "file": "CHANGELOG-v2.0.0.md", "categories": {}, "entries": []},
        {"version": "v1.0.0", "date": "2026-01-01T00:00:00Z", "file": "CHANGELOG-v1.0.0.md", "categories": {}, "entries": []},
    ]
    outputs = gen._all_outputs(model)
    unreleased = outputs[gen.REPO_ROOT / "CHANGELOG-unreleased.md"]
    v2 = outputs[gen.REPO_ROOT / "CHANGELOG-v2.0.0.md"]
    v1 = outputs[gen.REPO_ROOT / "CHANGELOG-v1.0.0.md"]
    assert "compare/v2.0.0...HEAD" in unreleased
    assert "compare/v1.0.0...v2.0.0" in v2
    assert "Full Changelog" not in v1


# --------------------------------------------------------------------------
# main(): write / check / cleanup
# --------------------------------------------------------------------------


@pytest.fixture()
def sandbox(monkeypatch, tmp_path):
    """Redirect every output path into a temp repo root."""
    config = tmp_path / ".github" / "release-drafter.yml"
    config.parent.mkdir(parents=True)
    config.write_text(CONFIG_YAML, encoding="utf-8")
    monkeypatch.setattr(gen, "REPO_ROOT", tmp_path)
    monkeypatch.setattr(gen, "CONFIG_PATH", config)
    monkeypatch.setattr(gen, "CHANGELOG_INDEX_MD", tmp_path / "CHANGELOG.md")
    monkeypatch.setattr(gen, "CHANGELOG_JSON", tmp_path / "docs" / "api" / "changelog.json")
    monkeypatch.setattr(
        gen, "CHANGELOG_INDEX_JSON", tmp_path / "docs" / "api" / "changelog-index.json"
    )
    monkeypatch.setattr(gen, "build_model", lambda repo, cfg: _model_with_one_pr())
    return tmp_path


def test_main_writes_all_artifacts(sandbox, monkeypatch):
    monkeypatch.setattr(sys, "argv", ["generate.py"])
    assert gen.main() == 0
    assert (sandbox / "CHANGELOG.md").is_file()
    assert (sandbox / "CHANGELOG-unreleased.md").is_file()
    assert (sandbox / "docs" / "api" / "changelog.json").is_file()
    assert (sandbox / "docs" / "api" / "changelog-index.json").is_file()


def test_main_check_passes_after_write(sandbox, monkeypatch):
    monkeypatch.setattr(sys, "argv", ["generate.py"])
    gen.main()
    monkeypatch.setattr(sys, "argv", ["generate.py", "--check"])
    assert gen.main() == 0


def test_main_check_fails_when_stale(sandbox, monkeypatch):
    monkeypatch.setattr(sys, "argv", ["generate.py"])
    gen.main()
    (sandbox / "CHANGELOG.md").write_text("tampered", encoding="utf-8")
    monkeypatch.setattr(sys, "argv", ["generate.py", "--check"])
    assert gen.main() == 1


def test_main_check_fails_on_orphan_version_file(sandbox, monkeypatch):
    monkeypatch.setattr(sys, "argv", ["generate.py"])
    gen.main()
    (sandbox / "CHANGELOG-v9.9.9.md").write_text("orphan", encoding="utf-8")
    monkeypatch.setattr(sys, "argv", ["generate.py", "--check"])
    assert gen.main() == 1


def test_main_removes_orphan_version_file(sandbox, monkeypatch):
    orphan = sandbox / "CHANGELOG-v9.9.9.md"
    orphan.write_text("orphan", encoding="utf-8")
    monkeypatch.setattr(sys, "argv", ["generate.py"])
    assert gen.main() == 0
    assert not orphan.exists()


def test_main_stdout_writes_nothing(sandbox, monkeypatch, capsys):
    monkeypatch.setattr(sys, "argv", ["generate.py", "--stdout"])
    assert gen.main() == 0
    assert json.loads(capsys.readouterr().out)["repository"] == "o/r"
    assert not (sandbox / "CHANGELOG.md").exists()


def test_load_config_missing_file(monkeypatch, tmp_path):
    monkeypatch.setattr(gen, "REPO_ROOT", tmp_path)
    monkeypatch.setattr(gen, "CONFIG_PATH", tmp_path / "nope.yml")
    with pytest.raises(SystemExit):
        gen.load_config()


def test_load_config_without_categories(monkeypatch, tmp_path):
    config = tmp_path / "release-drafter.yml"
    config.write_text("name-template: v1\n", encoding="utf-8")
    monkeypatch.setattr(gen, "CONFIG_PATH", config)
    with pytest.raises(SystemExit):
        gen.load_config()


# --------------------------------------------------------------------------
# HTTP seam (_token / _api_get / _api_get_all)
# --------------------------------------------------------------------------


class _FakeResponse:
    def __init__(self, payload):
        self._payload = payload

    def read(self):
        return json.dumps(self._payload).encode("utf-8")

    def __enter__(self):
        return self

    def __exit__(self, *exc):
        return False


def test_token_prefers_github_token(monkeypatch):
    monkeypatch.setenv("GITHUB_TOKEN", "a")
    monkeypatch.setenv("GH_TOKEN", "b")
    assert gen._token() == "a"


def test_token_falls_back_to_gh_token(monkeypatch):
    monkeypatch.delenv("GITHUB_TOKEN", raising=False)
    monkeypatch.setenv("GH_TOKEN", "b")
    assert gen._token() == "b"


def test_token_absent(monkeypatch):
    monkeypatch.delenv("GITHUB_TOKEN", raising=False)
    monkeypatch.delenv("GH_TOKEN", raising=False)
    assert gen._token() is None


class _RecordingOpener:
    """Stand-in for the module opener that records the outgoing request.

    Production code opens URLs through the module-level ``_OPENER`` (an
    HTTP(S)-only ``OpenerDirector``) rather than ``urllib.request.urlopen``, so
    that is the seam the tests patch.
    """

    def __init__(self, captured, payload):
        self._captured = captured
        self._payload = payload

    def open(self, req, timeout=30):
        self._captured["headers"] = dict(req.header_items())
        self._captured["url"] = req.full_url
        self._captured["timeout"] = timeout
        return _FakeResponse(self._payload)


def test_api_get_sends_auth_header_when_token_present(monkeypatch):
    captured = {}
    monkeypatch.setattr(gen, "_OPENER", _RecordingOpener(captured, {"ok": True}))
    monkeypatch.setenv("GITHUB_TOKEN", "secret-value")
    assert gen._api_get("/x") == {"ok": True}
    assert any(k.lower() == "authorization" for k in captured["headers"])


def test_api_get_omits_auth_header_without_token(monkeypatch):
    captured = {}
    monkeypatch.setattr(gen, "_OPENER", _RecordingOpener(captured, []))
    monkeypatch.delenv("GITHUB_TOKEN", raising=False)
    monkeypatch.delenv("GH_TOKEN", raising=False)
    gen._api_get("/x")
    assert not any(k.lower() == "authorization" for k in captured["headers"])


def test_api_get_applies_a_bounded_timeout(monkeypatch):
    captured = {}
    monkeypatch.setattr(gen, "_OPENER", _RecordingOpener(captured, {}))
    monkeypatch.delenv("GITHUB_TOKEN", raising=False)
    monkeypatch.delenv("GH_TOKEN", raising=False)
    gen._api_get("/x")
    assert captured["timeout"] == 30
    assert captured["url"].startswith(gen.API + "/")


@pytest.mark.parametrize(
    "bad_url",
    ["file:///etc/passwd", "ftp://example.com/x", "data:text/plain,x"],
)
def test_is_allowed_url_rejects_non_http_schemes(bad_url):
    assert gen._is_allowed_url(bad_url) is False


@pytest.mark.parametrize(
    "good_url",
    ["http://api.github.com", "https://api.github.com/x", "HTTPS://API.GITHUB.COM/x"],
)
def test_is_allowed_url_accepts_http_and_https(good_url):
    assert gen._is_allowed_url(good_url) is True


@pytest.mark.parametrize(
    "lookalike",
    ["httpsomething://x", "  file:///etc/passwd", "\tfile:///etc/passwd"],
)
def test_is_allowed_url_rejects_lookalikes(lookalike):
    assert gen._is_allowed_url(lookalike) is False


def test_api_get_rejects_an_unlisted_scheme_before_opening(monkeypatch):
    class ExplodingOpener:
        @staticmethod
        def open(req, timeout=30):  # pragma: no cover - must not be reached
            raise AssertionError("the opener must never be reached")

    monkeypatch.setattr(gen, "_OPENER", ExplodingOpener())
    with pytest.raises(ValueError, match="Unsupported URL scheme"):
        gen._api_get("httpfoo://evil/x")


def test_opener_cannot_open_file_or_ftp_urls():
    # Structural second line of defence: whatever the guard does, the module
    # opener must not carry a handler that can open `file:`/`ftp:`/`data:` URLs.
    handler_types = {type(handler) for handler in gen._OPENER.handlers}
    request = gen.urllib.request
    assert request.FileHandler not in handler_types
    assert request.FTPHandler not in handler_types
    assert request.DataHandler not in handler_types


@pytest.mark.parametrize(
    "blocked_url",
    ["file:///etc/passwd", "ftp://example.com/x", "data:text/plain,x"],
)
def test_opener_refuses_non_http_urls(blocked_url):
    # Behavioural proof that the structural check above is not cosmetic: the
    # real opener must reject a non-http(s) URL instead of opening it.
    with pytest.raises(gen.urllib.error.URLError):
        gen._OPENER.open(blocked_url, timeout=5)


def test_api_get_all_paginates(monkeypatch):
    pages = {1: [{"n": i} for i in range(100)], 2: [{"n": 100}]}

    def fake_get(path):
        import re as _re

        return pages.get(int(_re.search(r"[?&]page=(\d+)", path).group(1)), [])

    monkeypatch.setattr(gen, "_api_get", fake_get)
    assert len(gen._api_get_all("/x")) == 101


def test_api_get_all_stops_on_non_list(monkeypatch):
    monkeypatch.setattr(gen, "_api_get", lambda path: {"not": "a list"})
    assert gen._api_get_all("/x") == []


def test_fetch_tags_handles_commit_error(monkeypatch):
    import urllib.error

    monkeypatch.setattr(
        gen,
        "_api_get_all",
        lambda path: [{"name": "v1.0.0", "commit": {"sha": "s"}}],
    )

    def boom(path):
        raise urllib.error.HTTPError(path, 404, "nope", {}, None)

    monkeypatch.setattr(gen, "_api_get", boom)
    tags = gen.fetch_tags("o/r")
    assert tags[0]["date"] is None
