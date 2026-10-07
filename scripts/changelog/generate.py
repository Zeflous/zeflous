#!/usr/bin/env python3
"""Generate a per-version, machine-readable changelog from merged PRs.

WHAT THIS PRODUCES
------------------
A single in-memory model is rendered into several artifacts, so they can never
disagree:

  * ``CHANGELOG-vX.Y.Z.md``      -- one file PER RELEASE VERSION. Each merged
    PR is one block: the PR number + title, then the list of commits that
    belong to that PR (short SHA + subject). ``CHANGELOG-unreleased.md`` holds
    the PRs merged after the newest tag (or every PR when no tag exists yet).
  * ``CHANGELOG.md``             -- a LIGHTWEIGHT INDEX only: a table of links
    to each per-version file. It never carries the full history, so it cannot
    grow without bound.
  * ``docs/api/changelog.json``  -- the "changelog API": a static JSON document
    (served as-is from the repository, raw.githubusercontent.com, or GitHub
    Pages) that tooling can consume. Each entry carries the ``file`` it lives
    in and its ``commits`` (short SHA + subject), so a consumer never has to
    download a large file to read one PR.
  * ``docs/api/changelog-index.json`` -- a small index (version -> file, counts,
    latest release) for cheap discovery.

WHY ONE FILE PER VERSION
------------------------
A single ``CHANGELOG.md`` grows without bound as PRs accumulate, which is bad
for API consumers and for the file itself. Splitting per release keeps every
file small and bounded: a new release starts a new file, and the index stays a
constant-size table of links.

DESIGN CONSTRAINTS
------------------
* **Single source of truth for categories.** The category list and the
  label->category mapping are read from ``.github/release-drafter.yml`` -- the
  same file Release Drafter uses. There is no second, drifting copy of the
  taxonomy here.
* **Idempotent & deterministic.** Output is a pure function of (merged PRs,
  their commits, tags, config). Re-running never duplicates an entry: entries
  are de-duplicated by PR number within a version and commits are de-duplicated
  by SHA within a PR. ``as_of`` is derived from the data (newest merge time),
  never the wall clock, so ``--check`` can pass and a re-run is a no-op.
* **No secrets in output.** The token is read from the environment and never
  written to a file, log, or artifact.
* **Fail-closed on the config.** If the Release Drafter config cannot be read,
  the run stops rather than emitting a changelog with an invented taxonomy.

USAGE
-----
    python3 scripts/changelog/generate.py            # write the artifacts
    python3 scripts/changelog/generate.py --check    # exit 1 if they are stale
    python3 scripts/changelog/generate.py --stdout   # print JSON, write nothing

Environment:
    GITHUB_TOKEN       optional; raises the API rate limit and is required for
                       private repositories. Never printed.
    GITHUB_REPOSITORY  "owner/repo"; defaults to Zeflous/zeflous.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
import urllib.error
import urllib.request
from datetime import datetime
from pathlib import Path

try:
    import yaml
except ImportError:  # pragma: no cover - PyYAML is present in CI and locally
    sys.stderr.write("FATAL: PyYAML is required (pip install pyyaml)\n")
    raise SystemExit(2)

REPO_ROOT = Path(__file__).resolve().parents[2]
CONFIG_PATH = REPO_ROOT / ".github" / "release-drafter.yml"
CHANGELOG_INDEX_MD = REPO_ROOT / "CHANGELOG.md"
CHANGELOG_JSON = REPO_ROOT / "docs" / "api" / "changelog.json"
CHANGELOG_INDEX_JSON = REPO_ROOT / "docs" / "api" / "changelog-index.json"

DEFAULT_REPO = "Zeflous/zeflous"
API = "https://api.github.com"

# Category used for a PR whose labels match no configured category. Kept
# explicit so an unlabelled PR is visible rather than silently dropped.
FALLBACK_CATEGORY = "Other"

# Title prefix used by the changelog automation's own pull requests. Such PRs
# are bookkeeping (they only land the regenerated files) and are excluded from
# the changelog so the document never describes its own maintenance.
CHANGELOG_PR_PREFIX = "chore(changelog):"

# Conventional-Commit prefix -> category label, used only when a PR carries no
# labels at all. This mirrors the autolabeler rules in the Release Drafter
# config so a PR that has not been labelled yet still lands in the right bucket.
TITLE_PREFIX_LABELS = {
    "feat": "feature",
    "fix": "fix",
    "docs": "documentation",
    "chore": "chore",
    "ci": "ci",
    "test": "test",
    "refactor": "chore",
    "perf": "chore",
    "build": "chore",
    "style": "chore",
}

# The version bucket used for PRs merged after the newest tag (or for every PR
# when the repository has no tags yet).
UNRELEASED = "Unreleased"


def _token() -> str | None:
    return os.environ.get("GITHUB_TOKEN") or os.environ.get("GH_TOKEN")


def _api_get(path: str) -> object:
    """GET a GitHub API path, following pagination for list endpoints."""
    url = path if path.startswith("http") else f"{API}{path}"
    req = urllib.request.Request(url)
    req.add_header("Accept", "application/vnd.github+json")
    req.add_header("X-GitHub-Api-Version", "2022-11-28")
    token = _token()
    if token:
        req.add_header("Authorization", f"Bearer {token}")
    with urllib.request.urlopen(req, timeout=30) as resp:  # noqa: S310
        return json.loads(resp.read().decode("utf-8"))


def _api_get_all(path: str) -> list:
    """GET every page of a list endpoint (per_page=100)."""
    out: list = []
    page = 1
    while True:
        sep = "&" if "?" in path else "?"
        batch = _api_get(f"{path}{sep}per_page=100&page={page}")
        if not isinstance(batch, list) or not batch:
            break
        out.extend(batch)
        if len(batch) < 100:
            break
        page += 1
    return out


def load_config() -> dict:
    """Read the Release Drafter config -- the single source of the taxonomy."""
    if not CONFIG_PATH.is_file():
        sys.stderr.write(
            f"FATAL: {CONFIG_PATH.relative_to(REPO_ROOT)} is missing; refusing to "
            "invent a category taxonomy.\n"
        )
        raise SystemExit(2)
    with CONFIG_PATH.open(encoding="utf-8") as fh:
        cfg = yaml.safe_load(fh) or {}
    if not cfg.get("categories"):
        sys.stderr.write("FATAL: release-drafter.yml defines no categories.\n")
        raise SystemExit(2)
    return cfg


def category_for(labels: list[str], title: str, categories: list[dict]) -> str:
    """Map a PR to a category title using the configured label taxonomy."""
    lowered = {label.lower() for label in labels}
    for cat in categories:
        cat_labels = {str(x).lower() for x in (cat.get("labels") or [])}
        if lowered & cat_labels:
            return str(cat.get("title") or FALLBACK_CATEGORY)
    # No label matched: fall back to the Conventional-Commit prefix so an
    # unlabelled PR is still categorised rather than dumped into "Other".
    match = re.match(r"^([a-z]+)(\([^)]*\))?!?:", title.strip().lower())
    if match:
        implied = TITLE_PREFIX_LABELS.get(match.group(1))
        if implied:
            for cat in categories:
                cat_labels = {str(x).lower() for x in (cat.get("labels") or [])}
                if implied in cat_labels:
                    return str(cat.get("title") or FALLBACK_CATEGORY)
    return FALLBACK_CATEGORY


def _semver_key(tag: str) -> tuple:
    m = re.match(r"^v?(\d+)\.(\d+)\.(\d+)$", tag.strip())
    if not m:
        return (0, 0, 0)
    return tuple(int(x) for x in m.groups())


def version_filename(version: str) -> str:
    """The per-version markdown file name for a version bucket.

    ``Unreleased`` -> ``CHANGELOG-unreleased.md``; ``v1.2.3`` ->
    ``CHANGELOG-v1.2.3.md``. The name is derived from the version so it is
    stable across runs (idempotent) and safe as a path component.
    """
    if version == UNRELEASED:
        return "CHANGELOG-unreleased.md"
    safe = re.sub(r"[^A-Za-z0-9._-]", "-", version.strip())
    return f"CHANGELOG-{safe}.md"


def fetch_tags(repo: str) -> list[dict]:
    """Return tags with their commit date, sorted ascending by semver."""
    tags = _api_get_all(f"/repos/{repo}/tags")
    out = []
    for tag in tags:
        name = tag.get("name", "")
        commit_sha = (tag.get("commit") or {}).get("sha")
        date = None
        if commit_sha:
            try:
                commit = _api_get(f"/repos/{repo}/commits/{commit_sha}")
                date = (commit.get("commit", {}).get("committer") or {}).get("date")
            except urllib.error.HTTPError:
                date = None
        out.append({"name": name, "sha": commit_sha, "date": date})
    out.sort(key=lambda t: _semver_key(t["name"]))
    return out


def fetch_merged_prs(repo: str) -> list[dict]:
    """Return every merged PR, oldest first.

    Pull requests authored by the changelog automation itself are excluded:
    the update lane opens a PR to land the regenerated files, and that PR is
    bookkeeping, not a change to the project. Including it would make the
    changelog describe its own maintenance and would add a new entry on every
    regeneration cycle.
    """
    prs = _api_get_all(f"/repos/{repo}/pulls?state=closed&sort=created&direction=asc")
    merged = [
        p
        for p in prs
        if p.get("merged_at")
        and not (p.get("title") or "").startswith(CHANGELOG_PR_PREFIX)
    ]
    merged.sort(key=lambda p: p["merged_at"])
    return merged


def fetch_pr_commits(repo: str, number: int) -> list[dict]:
    """Return the commits that belong to a PR, oldest first.

    Each commit is reduced to ``{sha, short_sha, subject}``. The list is
    de-duplicated by SHA so a re-run (or a rebase that repeats a commit) can
    never duplicate a line.
    """
    try:
        raw = _api_get_all(f"/repos/{repo}/pulls/{number}/commits")
    except urllib.error.HTTPError:
        return []
    out: list[dict] = []
    seen: set[str] = set()
    for item in raw:
        sha = item.get("sha") or ""
        if not sha or sha in seen:
            continue
        seen.add(sha)
        message = ((item.get("commit") or {}).get("message") or "").strip()
        subject = message.splitlines()[0] if message else ""
        out.append({"sha": sha, "short_sha": sha[:7], "subject": subject})
    return out


def _parse_ts(value: str) -> datetime:
    return datetime.fromisoformat(value.replace("Z", "+00:00"))


def build_model(repo: str, cfg: dict) -> dict:
    categories = cfg.get("categories") or []
    tags = fetch_tags(repo)
    prs = fetch_merged_prs(repo)

    # A PR belongs to the FIRST release published at or after its merge time.
    # Anything merged after the newest tag is "Unreleased".
    versions: dict[str, dict] = {}
    order: list[str] = []

    def bucket(version: str) -> dict:
        if version not in versions:
            versions[version] = {
                "version": version,
                "date": None,
                "file": version_filename(version),
                "categories": {},
                "entries": [],
            }
            order.append(version)
        return versions[version]

    for pr in prs:
        merged_at = pr["merged_at"]
        target = UNRELEASED
        for tag in tags:
            if tag["date"] and _parse_ts(tag["date"]) >= _parse_ts(merged_at):
                target = tag["name"]
                break
        cat = category_for(
            [lbl.get("name", "") for lbl in pr.get("labels", [])],
            pr.get("title", ""),
            categories,
        )
        entry = {
            "pr": pr["number"],
            "title": pr.get("title", ""),
            "author": (pr.get("user") or {}).get("login", "unknown"),
            "category": cat,
            "merged_at": merged_at,
            "url": pr.get("html_url", ""),
            "file": version_filename(target),
            "commits": fetch_pr_commits(repo, pr["number"]),
        }
        b = bucket(target)
        # De-duplicate by PR number: re-running must never duplicate an entry.
        if not any(e["pr"] == entry["pr"] for e in b["entries"]):
            b["entries"].append(entry)

    # Attach each version's date from its tag (Unreleased has none).
    tag_dates = {t["name"]: t["date"] for t in tags}
    for name, b in versions.items():
        b["date"] = tag_dates.get(name)

    # Order: Unreleased first, then tags newest-first.
    ordered = []
    if UNRELEASED in versions:
        ordered.append(versions[UNRELEASED])
    for tag in reversed(tags):
        if tag["name"] in versions:
            ordered.append(versions[tag["name"]])

    # Group each version's entries by category, in the configured order.
    cat_titles = [str(c.get("title")) for c in categories] + [FALLBACK_CATEGORY]
    for b in ordered:
        grouped: dict[str, list] = {t: [] for t in cat_titles}
        for entry in sorted(b["entries"], key=lambda e: e["pr"]):
            grouped.setdefault(entry["category"], []).append(entry)
        b["categories"] = {k: v for k, v in grouped.items() if v}
        b["entries"] = sorted(b["entries"], key=lambda e: e["pr"])

    latest = tags[-1]["name"] if tags else None
    # `as_of` is the newest merge timestamp in the model, NOT the wall clock.
    # A wall-clock timestamp would make the document differ on every run, so
    # `--check` could never pass and the update job would commit on every push
    # even when nothing changed. Deriving it from the data keeps the output a
    # pure function of (merged PRs, tags, config) -- the property that makes
    # the whole lane idempotent.
    as_of = max((p["merged_at"] for p in prs), default=None)
    return {
        "schema_version": 2,
        "as_of": as_of,
        "repository": repo,
        "latest_release": latest,
        "unreleased_count": len(versions.get(UNRELEASED, {}).get("entries", [])),
        "versions": ordered,
    }


def render_version_markdown(version: dict) -> str:
    """Render ONE per-version file: a block per PR, commits beneath it."""
    name = version["version"]
    lines = [f"# CHANGELOG {name}", ""]
    lines.append("<!-- GENERATED FILE - do not edit by hand. -->")
    lines.append(
        "<!-- Regenerated by .github/workflows/changelog.yml from merged pull requests. -->"
    )
    lines.append("")
    if version.get("date"):
        lines.append(f"Release date: {version['date'][:10]}")
        lines.append("")
    lines.append(
        "Index: [`CHANGELOG.md`](CHANGELOG.md) · "
        "API: [`docs/api/changelog.json`](docs/api/changelog.json)"
    )
    lines.append("")
    if not version["entries"]:
        lines.append("- Tidak ada perubahan.")
        lines.append("")
        return "\n".join(lines).rstrip() + "\n"
    for entry in version["entries"]:
        lines.append(f"- PR#{entry['pr']:02d} {entry['title']}")
        if entry.get("commits"):
            for commit in entry["commits"]:
                subject = commit.get("subject") or ""
                lines.append(f"    - {commit['short_sha']} {subject}".rstrip())
        else:
            lines.append("    - (no commits recorded)")
        lines.append("")
    return "\n".join(lines).rstrip() + "\n"


def render_index_markdown(model: dict) -> str:
    """Render the lightweight index: links to each per-version file."""
    lines = [
        "# Changelog",
        "",
        "<!-- GENERATED FILE - do not edit by hand. -->",
        "<!-- Regenerated by .github/workflows/changelog.yml from merged pull requests. -->",
        "",
        "This is an index only. Each release has its own file so no single file",
        "grows without bound.",
        "",
        "Machine-readable form: [`docs/api/changelog.json`](docs/api/changelog.json) ·",
        "index: [`docs/api/changelog-index.json`](docs/api/changelog-index.json).",
        "",
        "| Version | Date | PRs | File |",
        "| --- | --- | --- | --- |",
    ]
    for version in model["versions"]:
        date = version["date"][:10] if version.get("date") else "—"
        lines.append(
            f"| {version['version']} | {date} | {len(version['entries'])} | "
            f"[{version['file']}]({version['file']}) |"
        )
    lines.append("")
    return "\n".join(lines).rstrip() + "\n"


def render_json(model: dict) -> str:
    return json.dumps(model, indent=2, ensure_ascii=False, sort_keys=False) + "\n"


def render_index_json(model: dict) -> str:
    """A small discovery index: version -> file, counts, latest release."""
    index = {
        "schema_version": model["schema_version"],
        "as_of": model["as_of"],
        "repository": model["repository"],
        "latest_release": model["latest_release"],
        "unreleased_count": model["unreleased_count"],
        "versions": [
            {
                "version": v["version"],
                "date": v["date"],
                "file": v["file"],
                "pr_count": len(v["entries"]),
            }
            for v in model["versions"]
        ],
    }
    return json.dumps(index, indent=2, ensure_ascii=False, sort_keys=False) + "\n"


def _all_outputs(model: dict) -> dict[Path, str]:
    """Every file the generator owns, as {path: content}."""
    outputs: dict[Path, str] = {
        CHANGELOG_INDEX_MD: render_index_markdown(model),
        CHANGELOG_JSON: render_json(model),
        CHANGELOG_INDEX_JSON: render_index_json(model),
    }
    for version in model["versions"]:
        outputs[REPO_ROOT / version["file"]] = render_version_markdown(version)
    return outputs


def _managed_version_files() -> set[Path]:
    """Existing per-version files the generator owns (for stale cleanup)."""
    return set(REPO_ROOT.glob("CHANGELOG-*.md"))


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true", help="exit 1 if stale")
    parser.add_argument("--stdout", action="store_true", help="print JSON only")
    args = parser.parse_args()

    repo = os.environ.get("GITHUB_REPOSITORY", DEFAULT_REPO)
    cfg = load_config()
    model = build_model(repo, cfg)

    if args.stdout:
        sys.stdout.write(render_json(model))
        return 0

    outputs = _all_outputs(model)

    if args.check:
        stale = []
        for path, content in outputs.items():
            if not path.is_file() or path.read_text(encoding="utf-8") != content:
                stale.append(str(path.relative_to(REPO_ROOT)))
        # A per-version file that is no longer produced (e.g. a version was
        # re-tagged) is stale too.
        for path in _managed_version_files() - set(outputs):
            stale.append(str(path.relative_to(REPO_ROOT)))
        if stale:
            sys.stderr.write("STALE: " + ", ".join(sorted(stale)) + "\n")
            return 1
        print("Changelog is up to date.")
        return 0

    # Remove per-version files that are no longer part of the model, so a
    # re-tag does not leave an orphan file behind.
    for path in _managed_version_files() - set(outputs):
        path.unlink()

    for path, content in outputs.items():
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content, encoding="utf-8")

    print(
        f"Wrote {len(outputs)} file(s): index + JSON + "
        f"{len(model['versions'])} version file(s) "
        f"({model['unreleased_count']} unreleased)."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
