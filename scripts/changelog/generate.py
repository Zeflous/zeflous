#!/usr/bin/env python3
"""Generate a machine-readable, per-version API changelog from merged PRs.

WHAT THIS PRODUCES
------------------
Two artifacts, both derived from the same in-memory model so they can never
disagree:

  * ``CHANGELOG.md``          -- human-readable, grouped by release version.
  * ``docs/api/changelog.json`` -- the "changelog API": a static JSON document
    that can be served as-is (raw.githubusercontent.com, GitHub Pages, or any
    static host) and consumed by tooling.

Each entry carries: PR number, title, author, category (feat/fix/chore/docs/...),
merge date, and the release version it belongs to. Entries are grouped by
version: PRs merged after the newest tag land under ``Unreleased``; PRs merged
before a tag land under that tag (``vX.Y.Z``).

DESIGN CONSTRAINTS
------------------
* **Single source of truth for categories.** The category list and the
  label->category mapping are read from ``.github/release-drafter.yml`` -- the
  same file Release Drafter uses. There is no second, drifting copy of the
  taxonomy here.
* **Idempotent.** Output is a pure function of (merged PRs, tags, config).
  Re-running never duplicates an entry: entries are de-duplicated by PR number
  within a version and the whole document is rebuilt deterministically.
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
CHANGELOG_MD = REPO_ROOT / "CHANGELOG.md"
CHANGELOG_JSON = REPO_ROOT / "docs" / "api" / "changelog.json"

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

    def bucket(version: str, date: str | None) -> dict:
        if version not in versions:
            versions[version] = {
                "version": version,
                "date": date,
                "categories": {},
                "entries": [],
            }
            order.append(version)
        return versions[version]

    for pr in prs:
        merged_at = pr["merged_at"]
        target = "Unreleased"
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
        }
        b = bucket(target, None)
        # De-duplicate by PR number: re-running must never duplicate an entry.
        if not any(e["pr"] == entry["pr"] for e in b["entries"]):
            b["entries"].append(entry)

    # Attach each version's date from its tag (Unreleased has none).
    tag_dates = {t["name"]: t["date"] for t in tags}
    for name, b in versions.items():
        b["date"] = tag_dates.get(name)

    # Order: Unreleased first, then tags newest-first.
    ordered = []
    if "Unreleased" in versions:
        ordered.append(versions["Unreleased"])
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
        "schema_version": 1,
        "as_of": as_of,
        "repository": repo,
        "latest_release": latest,
        "unreleased_count": len(versions.get("Unreleased", {}).get("entries", [])),
        "versions": ordered,
    }


def render_markdown(model: dict) -> str:
    lines = [
        "# Changelog",
        "",
        "<!-- GENERATED FILE - do not edit by hand. -->",
        "<!-- Regenerated by .github/workflows/changelog.yml from merged pull requests. -->",
        "",
        "Machine-readable form: [`docs/api/changelog.json`](docs/api/changelog.json).",
        "",
    ]
    for version in model["versions"]:
        heading = version["version"]
        if version.get("date"):
            heading += f" ({version['date'][:10]})"
        lines.append(f"## {heading}")
        lines.append("")
        if not version["categories"]:
            lines.append("- Tidak ada perubahan.")
            lines.append("")
            continue
        for cat, entries in version["categories"].items():
            lines.append(f"### {cat}")
            lines.append("")
            for e in entries:
                lines.append(f"- {e['title']} @{e['author']} (#{e['pr']})")
            lines.append("")
    return "\n".join(lines).rstrip() + "\n"


def render_json(model: dict) -> str:
    return json.dumps(model, indent=2, ensure_ascii=False, sort_keys=False) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true", help="exit 1 if stale")
    parser.add_argument("--stdout", action="store_true", help="print JSON only")
    args = parser.parse_args()

    repo = os.environ.get("GITHUB_REPOSITORY", DEFAULT_REPO)
    cfg = load_config()
    model = build_model(repo, cfg)
    md = render_markdown(model)
    js = render_json(model)

    if args.stdout:
        sys.stdout.write(js)
        return 0

    if args.check:
        stale = []
        if not CHANGELOG_MD.is_file() or CHANGELOG_MD.read_text(encoding="utf-8") != md:
            stale.append(str(CHANGELOG_MD.relative_to(REPO_ROOT)))
        if not CHANGELOG_JSON.is_file() or CHANGELOG_JSON.read_text(encoding="utf-8") != js:
            stale.append(str(CHANGELOG_JSON.relative_to(REPO_ROOT)))
        if stale:
            sys.stderr.write("STALE: " + ", ".join(stale) + "\n")
            return 1
        print("Changelog is up to date.")
        return 0

    CHANGELOG_MD.write_text(md, encoding="utf-8")
    CHANGELOG_JSON.parent.mkdir(parents=True, exist_ok=True)
    CHANGELOG_JSON.write_text(js, encoding="utf-8")
    print(
        f"Wrote {CHANGELOG_MD.relative_to(REPO_ROOT)} and "
        f"{CHANGELOG_JSON.relative_to(REPO_ROOT)} "
        f"({len(model['versions'])} version(s), "
        f"{model['unreleased_count']} unreleased)."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
