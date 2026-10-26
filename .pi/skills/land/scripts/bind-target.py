#!/usr/bin/env python3
"""Bind publication to the target's live default branch, policy and existing PR."""
from __future__ import annotations

import argparse
import datetime
import json
import os
from pathlib import Path
import re
import subprocess
import sys


class BindingError(ValueError):
    """Carry only fixed, public-safe recovery instructions."""


def run(root: Path, argv: list[str], timeout: int = 15) -> str:
    result = subprocess.run(argv, cwd=root, capture_output=True, text=True, check=True,
                            timeout=timeout, env={**os.environ, "GH_PROMPT_DISABLED": "1"})
    if len(result.stdout.encode("utf-8")) > 16384:
        raise ValueError("oversized metadata")
    return result.stdout.strip()


def bind(root: Path, policy: str, pr_url: str | None, head: str | None) -> dict:
    origin = run(root, ["git", "remote", "get-url", "origin"], 5)
    match = re.fullmatch(r"(?:git@github\.com:|https://github\.com/)([A-Za-z0-9_][A-Za-z0-9_.-]*/[A-Za-z0-9_][A-Za-z0-9_.-]*?)(?:\.git)?", origin)
    if not match:
        raise BindingError("resolve exact target GitHub origin")
    repository = match[1]
    target = "github.com/" + repository
    repository_url = "https://" + target
    metadata = json.loads(run(root, ["gh", "repo", "view", target, "--json", "nameWithOwner,url,defaultBranchRef"]))
    if (not isinstance(metadata, dict) or metadata.get("nameWithOwner") != repository
            or metadata.get("url") != repository_url):
        raise BindingError("live repository identity mismatch; recover the exact target repository")
    ref = metadata.get("defaultBranchRef")
    base = ref.get("name") if isinstance(ref, dict) else None
    if not isinstance(base, str) or not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._/-]*", base):
        raise BindingError("live default branch unavailable; recover repository metadata")
    run(root, ["git", "check-ref-format", "refs/heads/" + base], 5)
    if base != policy:
        raise BindingError("live default conflicts with target policy; requirement owner must explicitly dispose conflict before publication")
    result = {"host": "github.com", "repository": repository, "repository_url": repository_url,
              "base": base, "policy": policy,
              "observed_at": datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")}
    if pr_url:
        if not re.fullmatch(rf"https://github\.com/{re.escape(repository)}/pull/[1-9][0-9]*", pr_url):
            raise BindingError("existing PR does not identify target repository; recover the exact subject PR")
        pr = json.loads(run(root, ["gh", "pr", "view", pr_url, "--repo", target, "--json",
                                   "url,state,baseRefName,headRefName,headRefOid"]))
        if (not isinstance(pr, dict) or pr.get("url") != pr_url or pr.get("state") != "OPEN"
                or pr.get("baseRefName") != base or not head or pr.get("headRefName") != head
                or not isinstance(pr.get("headRefOid"), str)
                or not re.fullmatch(r"[0-9a-f]{40}", pr["headRefOid"])):
            raise BindingError("existing PR state/base/head conflict; resolve exact subject before publication")
        result.update(pr=pr_url, head=head, head_oid=pr["headRefOid"])
    return result


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, required=True)
    parser.add_argument("--policy", required=True, help="actual target policy branch, never a provider default")
    parser.add_argument("--pr", help="existing subject PR URL, when present")
    parser.add_argument("--head", help="intended existing PR head branch")
    args = parser.parse_args()
    try:
        result = bind(args.root.resolve(), args.policy, args.pr, args.head)
    except BindingError as error:
        print("Publication target HOLD: " + str(error), file=sys.stderr)
        return 1
    except (OSError, subprocess.SubprocessError, ValueError, TypeError, RecursionError):
        # No raw provider output or exception: either can contain private diagnostics.
        print("UNVERIFIED publication target: unavailable or conflicting repository/default/policy/PR evidence; hold publication and recover the exact identity or explicit policy disposition", file=sys.stderr)
        return 1
    print(json.dumps(result, sort_keys=True))
    return 0


if __name__ == "__main__":
    sys.exit(main())
