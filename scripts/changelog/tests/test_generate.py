"""Unit tests for the per-version changelog generator.

These live under ``scripts/changelog/`` (not ``tests/``) on purpose: the
repository's SonarCloud scope excludes ``scripts/**`` from analysis and
coverage, so the generator's own tests stay out of the PHP project's quality
gate while still running in CI. They are executed by the ``test`` job of
``.github/workflows/changelog.yml`` with a 90% coverage floor.

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
    {"title": "Maintenance", "labels": ["chore", "maintenance", "dependencies", "ci"]},
    {"title": "Documentation", "labels": ["documentation", "docs"]},
]

CONFIG_YAML = """
categories:
  - title: "Features"
    labels: ["feature", "enhancement"]
  - title: "Bug Fixes"
    labels: ["fix", "bugfix", "bug"]
  - title: "Maintenance"
    labels: ["chore", "maintenance", "dependencies", "ci"]
  - title: "Documentation"
    labels: ["documentation", "docs"]
"""


def _pr(number, title, merged_at, labels=None, author="alice"):
    return {
        "number": number,
        "title": title,
        "merged_at": merged_at,
        "labels": [{"name": x} for x in (labels or [])],
        "user": {"login": author},
        "html_url": f"https://github.com/o/r/pull/{number}",
    }


def _commit(sha, message):
    return {"sha": sha, "commit": {"message": message}}


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


def test_category_for_matches_label():
    assert gen.category_for(["feature"], "anything", CATEGORIES) == "Features"


def test_category_for_is_case_insensitive():
    assert gen.category_for(["FIX"], "anything", CATEGORIES) == "Bug Fixes"


def test_category_for_falls_back_to_title_prefix():
    assert gen.category_for([], "feat(scope): add thing", CATEGORIES) == "Features"


def test_category_for_unknown_is_other():
    assert gen.category_for([], "random title", CATEGORIES) == "Other"


def test_category_for_unknown_prefix_is_other():
    assert gen.category_for([], "wibble: nope", CATEGORIES) == "Other"


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
# Model building
# --------------------------------------------------------------------------


def _patch_api(monkeypatch, tags, prs, commits_by_pr):
    def fake_get_all(path):
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


def test_build_model_dedupes_pr_number(monkeypatch):
    prs = [
        _pr(1, "feat: one", "2026-01-01T00:00:00Z", ["feature"]),
        _pr(1, "feat: one", "2026-01-01T00:00:00Z", ["feature"]),
    ]
    _patch_api(monkeypatch, [], prs, {1: []})
    model = gen.build_model("o/r", {"categories": CATEGORIES})
    assert len(model["versions"][0]["entries"]) == 1


def test_build_model_no_tags_is_all_unreleased(monkeypatch):
    prs = [_pr(1, "feat: one", "2026-01-01T00:00:00Z", ["feature"])]
    _patch_api(monkeypatch, [], prs, {1: []})
    model = gen.build_model("o/r", {"categories": CATEGORIES})
    assert [v["version"] for v in model["versions"]] == ["Unreleased"]
    assert model["latest_release"] is None


# --------------------------------------------------------------------------
# Rendering
# --------------------------------------------------------------------------


def _model_with_one_pr():
    return {
        "schema_version": 2,
        "as_of": "2026-01-01T00:00:00Z",
        "repository": "o/r",
        "latest_release": None,
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


def test_render_version_markdown_has_pr_block_and_commits():
    md = gen.render_version_markdown(_model_with_one_pr()["versions"][0])
    assert md.startswith("# CHANGELOG Unreleased")
    assert "- PR#01 feat: one" in md
    assert f"    - {'a' * 7} feat: one" in md


def test_render_version_markdown_without_commits():
    version = _model_with_one_pr()["versions"][0]
    version["entries"][0]["commits"] = []
    md = gen.render_version_markdown(version)
    assert "(no commits recorded)" in md


def test_render_version_markdown_with_release_date():
    version = _model_with_one_pr()["versions"][0]
    version["date"] = "2026-03-04T00:00:00Z"
    md = gen.render_version_markdown(version)
    assert "Release date: 2026-03-04" in md


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


def test_render_json_roundtrips():
    model = _model_with_one_pr()
    assert json.loads(gen.render_json(model)) == model


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


def test_api_get_sends_auth_header_when_token_present(monkeypatch):
    captured = {}

    def fake_urlopen(req, timeout=30):
        captured["headers"] = dict(req.header_items())
        return _FakeResponse({"ok": True})

    monkeypatch.setattr(gen.urllib.request, "urlopen", fake_urlopen)
    monkeypatch.setenv("GITHUB_TOKEN", "secret-value")
    assert gen._api_get("/x") == {"ok": True}
    assert any(k.lower() == "authorization" for k in captured["headers"])


def test_api_get_omits_auth_header_without_token(monkeypatch):
    captured = {}

    def fake_urlopen(req, timeout=30):
        captured["headers"] = dict(req.header_items())
        return _FakeResponse([])

    monkeypatch.setattr(gen.urllib.request, "urlopen", fake_urlopen)
    monkeypatch.delenv("GITHUB_TOKEN", raising=False)
    monkeypatch.delenv("GH_TOKEN", raising=False)
    gen._api_get("/x")
    assert not any(k.lower() == "authorization" for k in captured["headers"])


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
