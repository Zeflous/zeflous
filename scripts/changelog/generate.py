#!/usr/bin/env python3
"""Generate a per-version, machine-readable changelog from merged PRs.

WHAT THIS PRODUCES
------------------
A single in-memory model is rendered into several artifacts, so they can never
disagree:

  * ``CHANGELOG-vX.Y.Z.md``      -- one file PER RELEASE VERSION, written in
    the STYLE of the reference project github-changelog-generator (a ``##``
    release heading with a release/date link, a ``[Full Changelog]`` compare
    link, and ``**Category:**`` blocks of ``- <title> [#<n>](<url>)
    ([<author>](<author-url>))`` entries). Unlike the reference tool -- which
    emits ONE flat file -- there is still one file per version. Each entry is a
    SINGLE line: the PR title with its PR link (the reference tool's shape).
    The per-commit lines were removed because they duplicated the PR title and
    made the file noisy; the commits remain in ``docs/api/changelog.json``, so
    the machine-readable API is unchanged.
    ``CHANGELOG-unreleased.md`` holds the PRs merged after the newest release
    (or every PR when no release exists yet).
  * ``CHANGELOG.md``             -- a LIGHTWEIGHT INDEX only: a table of links
    to each per-version file. It never carries the full history, so it cannot
    grow without bound.
  * ``docs/api/changelog.json``  -- the "changelog API": a static JSON document
    (served as-is from the repository, or via raw.githubusercontent.com) that
    tooling can consume. Each entry carries the ``file`` it lives in and its
    ``commits`` (short SHA + subject), so a consumer never has to download a
    large file to read one PR. NOTE: this repository has no GitHub Pages
    deployment, so the JSON is NOT served from Pages.
  * ``docs/api/changelog-index.json`` -- a small index (version -> file, counts,
    latest release) for cheap discovery.

WHY ONE FILE PER VERSION
------------------------
A single ``CHANGELOG.md`` grows without bound as PRs accumulate, which is bad
for API consumers and for the file itself. Splitting per release keeps every
file small and bounded: a new release starts a new file, and the index stays a
constant-size table of links.

VERSION SOURCE: RELEASE, NOT TAG
--------------------------------
The version a PR belongs to is decided by the project's **releases**, not by
raw git tags. The source of truth is the GitHub Releases API
(``GET /repos/{owner}/{repo}/releases``): a PR belongs to the FIRST *published*
release whose publish time is at or after the PR's merge time. A draft release
is not yet a release, so it never buckets a PR; it is surfaced only as the
``next_release`` hint. Git tags are used ONLY as a fallback when the repository
has no published releases at all, so a tag-only repository still works. The
chosen source is recorded in the model as ``version_source``
(``github_release`` / ``git_tag`` / ``none``).

DESIGN CONSTRAINTS
------------------
* **Merged-only.** Only pull requests that were actually MERGED into the
  default branch are included. A PR that was closed without being merged, a
  draft PR, and a PR merged into a non-default branch are all excluded
  explicitly -- they never changed the default branch, so they must not appear
  in the changelog. See ``fetch_merged_prs``.
* **Single source of truth for categories.** The category list and the
  label->category mapping are read from ``.github/release-drafter.yml`` -- the
  same file Release Drafter uses. There is no second, drifting copy of the
  taxonomy here. A PR with no matching label is categorised from its
  Conventional-Commit type (``feat:``/``fix:``/...) via ``CONVENTIONAL_CATEGORY``,
  which maps each type onto one of those SAME configured titles, so a
  regex-derived category can never conflict with a label-derived one.
* **Idempotent & deterministic.** Output is a pure function of (merged PRs,
  their commits, releases, config). Re-running never duplicates an entry:
  entries are de-duplicated by PR number within a version and commits are
  de-duplicated by SHA within a PR. ``as_of`` is derived from the data (newest
  merge time), never the wall clock, so ``--check`` can pass and a re-run is a
  no-op.
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
import urllib.parse
import urllib.request
from datetime import datetime
from pathlib import Path

# The Conventional-Commit taxonomy lives in a shared module so the changelog
# generator and the PR Validator gate import the SAME rules (no drift). The
# script's own directory is put on sys.path so the import works both when run
# as `python3 scripts/changelog/generate.py` and when the test suite loads this
# file by path.
sys.path.insert(0, str(Path(__file__).resolve().parent))
from conventional import CONVENTIONAL_CATEGORY, CONVENTIONAL_RE  # noqa: E402

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

# Title prefixes used by the changelog automation's own pull requests. Such
# PRs are bookkeeping (they only land the regenerated files) and are excluded
# from the changelog so the document never describes its own maintenance.
#
# There are TWO automation lanes, so there are TWO prefixes: the per-version
# lane opens `chore(changelog): ...` and the official-generator lane opens
# `chore(changelog-official): ...`. Both must be excluded -- excluding only the
# first let the official lane's bookkeeping PRs leak into the per-version
# changelog (66 lines of self-description, observed on `main`). Kept as a tuple
# so a future lane only has to add its prefix here.
CHANGELOG_PR_PREFIXES: tuple[str, ...] = (
    "chore(changelog):",
    "chore(changelog-official):",
)

# The branch a PR must have been merged INTO to count as a change to the
# project. A PR merged into some other branch (a release branch, a backport
# branch, ...) never lands on the default branch, so it must not appear in the
# changelog. Overridable via GITHUB_DEFAULT_BRANCH for forks/renames.
DEFAULT_BRANCH = os.environ.get("GITHUB_DEFAULT_BRANCH", "main")

# Conventional-Commit type -> Release Drafter category TITLE, and the header
# regex, are imported from `scripts/changelog/conventional.py` (see the import
# above). That module is the SINGLE SOURCE OF TRUTH: the PR Validator gate
# imports the very same rules, so a title that passes the gate can never be
# categorised differently here. The mapped titles are exactly the ones
# configured in `.github/release-drafter.yml`, so a regex-derived category can
# never conflict with a label-derived one.

# The version bucket used for PRs merged after the newest release (or for every
# PR when the repository has no releases and no tags yet).
UNRELEASED = "Unreleased"


def _token() -> str | None:
    return os.environ.get("GITHUB_TOKEN") or os.environ.get("GH_TOKEN")


# The only URL schemes this script may ever open. `urllib.request.urlopen`
# happily honours `file://`, `ftp://` and custom schemes, so a URL that an
# external party can influence would otherwise let the CI runner read local
# files (`file:///etc/...`). The API base (`API`) is HTTPS and callers only pass
# `API`-relative paths or absolute API URLs, so nothing legitimate needs a
# scheme outside this allow-list. Named at module level so the guard and its
# tests share one source of truth.
ALLOWED_URL_SCHEMES = ("http", "https")


def _is_allowed_url(url: str) -> bool:
    """Return True when ``url`` uses an absolute, allow-listed scheme.

    ``urllib.parse.urlsplit`` is used instead of a substring check so a
    look-alike such as ``httpsomething://`` -- or a scheme hidden behind the
    leading whitespace that ``urlopen`` would ignore -- cannot slip through:
    ``urlsplit`` strips those characters, then reports the real scheme.
    """
    return urllib.parse.urlsplit(url).scheme in ALLOWED_URL_SCHEMES


# Handlers that can open something other than an http(s) URL. They are stripped
# from the opener below, so the opener can only ever speak HTTP(S).
_BLOCKED_URL_HANDLERS = (
    urllib.request.FileHandler,
    urllib.request.FTPHandler,
    urllib.request.DataHandler,
)


def _build_http_only_opener() -> urllib.request.OpenerDirector:
    """Build an ``OpenerDirector`` that can only ever open http(s) URLs.

    ``urllib.request.build_opener()`` installs ``FileHandler``, ``FTPHandler``
    and ``DataHandler`` among its defaults, and passing extra handler classes
    does NOT remove them -- it only prepends. A ``file://`` URL handed to such
    an opener would therefore read a local file on the CI runner, which is the
    very risk the scheme guard above exists to prevent. This strips the blocked
    handlers AND their protocol registrations, so ``open('file:...')`` fails
    with ``URLError`` (unknown url type) instead of touching the disk. The
    scheme allow-list is the first line of defence; this opener is the second.
    """

    opener = urllib.request.build_opener()

    for handler in list(opener.handlers):
        if isinstance(handler, _BLOCKED_URL_HANDLERS):
            opener.handlers.remove(handler)

    for protocol in ("file", "ftp", "data"):
        opener.handle_open.pop(protocol, None)

    return opener


# HTTP(S)-only opener. Both defences are covered by regression tests so neither
# can be removed silently.
_OPENER = _build_http_only_opener()


def _api_get(path: str) -> object:
    """GET a GitHub API path, following pagination for list endpoints."""
    url = path if path.startswith("http") else f"{API}{path}"
    if not _is_allowed_url(url):
        raise ValueError(f"Unsupported URL scheme: {url}")
    req = urllib.request.Request(url)
    req.add_header("Accept", "application/vnd.github+json")
    req.add_header("X-GitHub-Api-Version", "2022-11-28")
    token = _token()
    if token:
        req.add_header("Authorization", f"Bearer {token}")
    with _OPENER.open(req, timeout=30) as resp:  # noqa: S310 - scheme-guarded, HTTP(S)-only opener
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


def conventional_type(text: str) -> str | None:
    """Return the Conventional-Commit type of a title/subject, or ``None``.

    ``"feat(api)!: add thing"`` -> ``"feat"``; ``"random title"`` -> ``None``.
    """
    match = CONVENTIONAL_RE.match((text or "").strip().lower())
    return match.group(1) if match else None


def category_for(
    labels: list[str],
    title: str,
    categories: list[dict],
    commit_subjects: tuple[str, ...] | list[str] = (),
) -> str:
    """Map a PR to a category title.

    Resolution order, first hit wins:

    1. A configured label on the PR (Release Drafter's own taxonomy).
    2. The Conventional-Commit type of the PR title, mapped through
       ``CONVENTIONAL_CATEGORY`` onto a configured title.
    3. The Conventional-Commit type of the PR's commit subjects (same mapping),
       so a PR whose title is not conventional but whose commits are still
       lands in the right bucket.
    4. ``FALLBACK_CATEGORY`` ("Other").
    """
    lowered = {label.lower() for label in labels}
    for cat in categories:
        cat_labels = {str(x).lower() for x in (cat.get("labels") or [])}
        if lowered & cat_labels:
            return str(cat.get("title") or FALLBACK_CATEGORY)
    # No label matched: fall back to the Conventional-Commit type so an
    # unlabelled PR is still categorised rather than dumped into "Other".
    configured_titles = {str(c.get("title")) for c in categories}
    for text in (title, *commit_subjects):
        ctype = conventional_type(text)
        if ctype:
            mapped = CONVENTIONAL_CATEGORY.get(ctype)
            if mapped and mapped in configured_titles:
                return mapped
    return FALLBACK_CATEGORY


def _semver_key(tag: str) -> tuple:
    m = re.match(r"^v?(\d+)\.(\d+)\.(\d+)$", (tag or "").strip())
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


def fetch_releases(repo: str) -> list[dict]:
    """Return PUBLISHED (non-draft) releases, sorted ascending by semver.

    This is the version source of truth: a release is what the project has
    actually shipped. A draft release is not yet a release, so it is excluded
    here (it is surfaced separately as the ``next_release`` hint).
    """
    raw = _api_get_all(f"/repos/{repo}/releases")
    out = []
    for rel in raw:
        if rel.get("draft"):
            continue
        name = rel.get("tag_name") or rel.get("name") or ""
        if not name:
            continue
        out.append({"name": name, "date": rel.get("published_at")})
    out.sort(key=lambda r: _semver_key(r["name"]))
    return out


def fetch_next_release(repo: str) -> str | None:
    """The newest DRAFT release's tag, used as the "next version" hint.

    Release Drafter keeps a draft release for the upcoming version; surfacing
    its tag lets a consumer label the ``Unreleased`` bucket without treating
    the draft as a shipped release.
    """
    raw = _api_get_all(f"/repos/{repo}/releases")
    drafts = [r for r in raw if r.get("draft") and (r.get("tag_name") or r.get("name"))]
    if not drafts:
        return None
    drafts.sort(key=lambda r: _semver_key(r.get("tag_name") or r.get("name") or ""))
    return drafts[-1].get("tag_name") or drafts[-1].get("name")


def fetch_tags(repo: str) -> list[dict]:
    """Return tags with their commit date, sorted ascending by semver.

    Used ONLY as a fallback version source when the repository has no published
    releases at all, so a tag-only repository still produces a changelog.
    """
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
    """Return every PR that was actually MERGED into the default branch.

    A PR only changes the project once it is merged into the default branch.
    The changelog must therefore contain exactly those PRs, and nothing else.
    The selection is explicit and fail-closed on every exclusion:

    * ``merged_at`` must be set. A PR that was closed WITHOUT being merged
      (``state == "closed"`` and ``merged_at is None``) never landed on the
      default branch, so it is excluded. Filtering on ``state == "closed"``
      alone would wrongly include it -- that is the bug this guard prevents.
    * ``draft`` PRs are excluded: a draft is not a completed change.
    * PRs merged into a branch other than the default branch are excluded:
      they never reached the default branch.
    * PRs authored by the changelog automation itself are excluded: BOTH
      update lanes (per-version and official) open a PR to land the regenerated
      files, and those PRs are bookkeeping, not a change to the project.
      Including them would make the changelog describe its own maintenance and
      would add a new entry on every regeneration cycle. The exclusion matches
      every prefix in ``CHANGELOG_PR_PREFIXES``.

    GitHub's REST API does not expose an "archived" flag on a pull request
    (archiving applies to repositories, not PRs), so there is no archived-PR
    field to test; the ``merged_at`` + default-branch guards already exclude
    every PR that did not land on the default branch, which is the property
    that matters. The guard is written so that if such a field ever appears it
    is honoured too.
    """
    prs = _api_get_all(f"/repos/{repo}/pulls?state=closed&sort=created&direction=asc")
    merged = [
        p
        for p in prs
        if p.get("merged_at")  # merged, not merely closed
        and not p.get("draft")  # a draft is not a completed change
        and not p.get("archived")  # honoured if the API ever exposes it
        and (p.get("base") or {}).get("ref", DEFAULT_BRANCH) == DEFAULT_BRANCH
        and not (p.get("title") or "").startswith(CHANGELOG_PR_PREFIXES)
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


def _version_markers(repo: str) -> tuple[list[dict], str]:
    """Return the (markers, version_source) used to bucket PRs by version.

    Markers are the published releases when any exist (``github_release``);
    otherwise the git tags (``git_tag``); otherwise nothing (``none``), in
    which case every PR is ``Unreleased``.
    """
    releases = fetch_releases(repo)
    if releases:
        return releases, "github_release"
    tags = fetch_tags(repo)
    if tags:
        return [{"name": t["name"], "date": t["date"]} for t in tags], "git_tag"
    return [], "none"


def _get_target_version(merged_at: str, markers: list[dict]) -> str:
    """Return the FIRST release published at or after ``merged_at``.

    Anything merged after the newest release is ``Unreleased``. Both sides are
    parsed to ``datetime`` before comparison: ``merged_at`` and
    ``marker["date"]`` are ISO strings, so a raw comparison would be a string
    comparison (and comparing a string to a datetime would raise ``TypeError``).
    """
    merged_ts = _parse_ts(merged_at)
    for marker in markers:
        if marker["date"] and _parse_ts(marker["date"]) >= merged_ts:
            return marker["name"]
    return UNRELEASED


def _build_entry(
    pr: dict, target: str, commits: list[dict], categories: list[dict]
) -> dict:
    """Build one changelog entry, preserving the renderer's expected schema.

    The keys here are the contract consumed by ``render_version_markdown`` and
    ``render_json`` (``pr``, ``category``, ``commits``, ``url``, ``file`` ...);
    changing them silently breaks the generated ``docs/api/changelog.json``.
    """
    cat = category_for(
        [lbl.get("name", "") for lbl in pr.get("labels", [])],
        pr.get("title", ""),
        categories,
        [c.get("subject", "") for c in commits],
    )
    return {
        "pr": pr["number"],
        "title": pr.get("title", ""),
        "author": (pr.get("user") or {}).get("login", "unknown"),
        "category": cat,
        "merged_at": pr["merged_at"],
        "url": pr.get("html_url", ""),
        "file": version_filename(target),
        "commits": commits,
    }


def _collect_versions(
    repo: str, prs: list[dict], markers: list[dict], categories: list[dict]
) -> dict[str, dict]:
    """Bucket every merged PR into its target version, de-duplicated by PR number.

    A PR belongs to the FIRST release published at or after its merge time;
    anything merged after the newest release is ``Unreleased``.
    """
    versions: dict[str, dict] = {}

    def bucket(version: str) -> dict:
        if version not in versions:
            versions[version] = {
                "version": version,
                "date": None,
                "file": version_filename(version),
                "categories": {},
                "entries": [],
            }
        return versions[version]

    for pr in prs:
        target = _get_target_version(pr["merged_at"], markers)
        commits = fetch_pr_commits(repo, pr["number"])
        entry = _build_entry(pr, target, commits, categories)
        b = bucket(target)
        # De-duplicate by PR number: re-running must never duplicate an entry.
        if not any(e["pr"] == entry["pr"] for e in b["entries"]):
            b["entries"].append(entry)
    return versions


def _order_versions(versions: dict[str, dict], markers: list[dict]) -> list[dict]:
    """Order: Unreleased first, then releases newest-first."""
    ordered = []
    if UNRELEASED in versions:
        ordered.append(versions[UNRELEASED])
    for marker in reversed(markers):
        if marker["name"] in versions:
            ordered.append(versions[marker["name"]])
    return ordered


def _group_by_category(ordered: list[dict], categories: list[dict]) -> None:
    """Group each version's entries by category, in the configured order."""
    cat_titles = [str(c.get("title")) for c in categories] + [FALLBACK_CATEGORY]
    for b in ordered:
        grouped: dict[str, list] = {t: [] for t in cat_titles}
        for entry in sorted(b["entries"], key=lambda e: e["pr"]):
            grouped.setdefault(entry["category"], []).append(entry)
        b["categories"] = {k: v for k, v in grouped.items() if v}
        b["entries"] = sorted(b["entries"], key=lambda e: e["pr"])


def build_model(repo: str, cfg: dict) -> dict:
    categories = cfg.get("categories") or []
    markers, version_source = _version_markers(repo)
    next_release = fetch_next_release(repo)
    prs = fetch_merged_prs(repo)

    versions = _collect_versions(repo, prs, markers, categories)

    # Attach each version's date from its release/tag (Unreleased has none).
    marker_dates = {m["name"]: m["date"] for m in markers}
    for name, b in versions.items():
        b["date"] = marker_dates.get(name)

    ordered = _order_versions(versions, markers)
    _group_by_category(ordered, categories)

    latest = markers[-1]["name"] if markers else None
    # `as_of` is the newest merge timestamp in the model, NOT the wall clock.
    # A wall-clock timestamp would make the document differ on every run, so
    # `--check` could never pass and the update job would commit on every push
    # even when nothing changed. Deriving it from the data keeps the output a
    # pure function of (merged PRs, releases, config) -- the property that
    # makes the whole lane idempotent.
    as_of = max((p["merged_at"] for p in prs), default=None)
    return {
        "schema_version": 3,
        "as_of": as_of,
        "repository": repo,
        "version_source": version_source,
        "latest_release": latest,
        "next_release": next_release,
        "unreleased_count": len(versions.get(UNRELEASED, {}).get("entries", [])),
        "versions": ordered,
    }


def _repo_slug(repo: str | None, version: dict) -> str:
    """The ``owner/name`` slug used to build links inside a per-version file.

    The model carries ``repository``; when the renderer is called directly
    (tests) without it, the slug is inferred from an entry's PR URL, so the
    links stay correct for any repository. ``DEFAULT_REPO`` is the last resort.
    """
    if repo:
        return repo
    for entry in version.get("entries", []):
        match = re.match(
            r"https?://github\.com/([^/]+/[^/]+)/pull/\d+", entry.get("url") or ""
        )
        if match:
            return match.group(1)
    return DEFAULT_REPO


def _grouped_entries(version: dict) -> dict[str, list]:
    """Return ``version``'s entries grouped by category, in the model's order.

    ``version["categories"]`` holds the configured category ORDER, but a
    hand-built model (tests) may leave it empty while still carrying entries,
    so the grouping is rebuilt from ``entries`` and merely ordered by that
    mapping. A category present only in the entries is appended last.
    """
    order = list(version.get("categories", {}).keys())
    grouped: dict[str, list] = {}
    for entry in version.get("entries", []):
        grouped.setdefault(entry.get("category") or FALLBACK_CATEGORY, []).append(entry)
    ordered = {cat: grouped.pop(cat) for cat in order if cat in grouped}
    ordered.update(grouped)
    return ordered


def _render_entry_line(entry: dict) -> str:
    """One reference-style entry line.

    ``- <title> [#<n>](<pr-url>) ([<author>](<author-url>))`` -- the shape
    github-changelog-generator emits, so the two tools read alike. A missing PR
    URL degrades to a bare ``#<n>`` and a missing author to ``unknown``.
    """
    title = (entry.get("title") or "").strip()
    line = f"- {title}"
    url = entry.get("url") or ""
    if url:
        line += f" [#{entry['pr']}]({url})"
    else:
        line += f" #{entry['pr']}"
    author = entry.get("author") or "unknown"
    line += f" ([{author}](https://github.com/{author}))"
    return line


def render_version_markdown(
    version: dict, repo: str | None = None, previous: str | None = None
) -> str:
    """Render ONE per-version file in the github-changelog-generator style.

    The layout mirrors the reference project's ``CHANGELOG.md`` (see
    github-changelog-generator/github-changelog-generator) but is scoped to a
    SINGLE version per file, as this repository requires::

        # Changelog

        ## [v1.2.3](release-url) (YYYY-MM-DD)

        [Full Changelog](compare-url)

        **Features:**

        - <title> [#12](pr-url) ([author](author-url))

    Each entry is a SINGLE line -- the PR title with its PR link -- exactly the
    reference tool's shape. The per-commit lines were removed: they duplicated
    the PR title and made the file noisy. The commits are still carried by
    ``docs/api/changelog.json``, so the machine-readable API is unchanged.
    ``Unreleased`` gets no tag link and no date; the ``[Full Changelog]`` line
    appears only when an older version exists to compare against.
    """
    name = version["version"]
    slug = _repo_slug(repo, version)
    is_release = name != UNRELEASED

    lines = [
        "# Changelog",
        "",
        "<!-- GENERATED FILE - do not edit by hand. -->",
        "<!-- Regenerated by .github/workflows/changelog.yml from merged pull requests. -->",
        "",
        "Index: [`CHANGELOG.md`](CHANGELOG.md) \u00b7 "
        "API: [`docs/api/changelog.json`](docs/api/changelog.json)",
        "",
    ]

    # `## [vX.Y.Z](tree-url) (YYYY-MM-DD)` for a release; a bare `## Unreleased`
    # for the not-yet-released bucket, which has neither a tag nor a date.
    if is_release:
        heading = f"## [{name}](https://github.com/{slug}/tree/{name})"
        if version.get("date"):
            heading += f" ({version['date'][:10]})"
    else:
        heading = "## Unreleased"
    lines.extend([heading, ""])

    # `[Full Changelog](compare-url)` -- only when an older version exists.
    # `Unreleased` compares up to HEAD; a release compares to its own tag.
    if previous:
        target = name if is_release else "HEAD"
        lines.extend(
            [
                f"[Full Changelog](https://github.com/{slug}/compare/{previous}...{target})",
                "",
            ]
        )

    grouped = _grouped_entries(version)
    if not grouped:
        lines.extend(["- Tidak ada perubahan.", ""])
    else:
        for category, entries in grouped.items():
            lines.extend([f"**{category}:**", ""])
            for entry in entries:
                lines.append(_render_entry_line(entry))
            lines.append("")

    lines.extend(
        [
            "\\* *This file was generated automatically by "
            "[`scripts/changelog/generate.py`](scripts/changelog/generate.py) "
            "from merged pull requests.*",
            "",
        ]
    )
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
        "Machine-readable form: [`docs/api/changelog.json`](docs/api/changelog.json) \u00b7",
        "index: [`docs/api/changelog-index.json`](docs/api/changelog-index.json).",
        "",
        "| Version | Date | PRs | File |",
        "| --- | --- | --- | --- |",
    ]
    for version in model["versions"]:
        date = version["date"][:10] if version.get("date") else "\u2014"
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
        "version_source": model["version_source"],
        "latest_release": model["latest_release"],
        "next_release": model["next_release"],
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
    versions = model["versions"]
    for index, version in enumerate(versions):
        # Versions are ordered newest-first, so the NEXT entry is the older
        # release to compare against (the `[Full Changelog]` link). The oldest
        # version has none.
        previous = versions[index + 1]["version"] if index + 1 < len(versions) else None
        outputs[REPO_ROOT / version["file"]] = render_version_markdown(
            version, repo=model.get("repository"), previous=previous
        )
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
        f"({model['unreleased_count']} unreleased, source={model['version_source']})."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
