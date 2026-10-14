#!/usr/bin/env python3
"""Validate local HTTPS configuration and update only its browser origin."""

import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parent.parent
ENV = ROOT / ".env"
STATE = ROOT / ".runs" / "https"
ORIGIN = "https://localhost:18443"


def environment():
    """Read effective Compose settings without printing resolved credentials."""
    if not ENV.is_file() or ENV.is_symlink():
        raise ValueError("Create a regular .env file before HTTPS setup.")
    ignored = subprocess.run(
        ["git", "check-ignore", "--quiet", ".env"], cwd=ROOT, capture_output=True
    )
    if ignored.returncode != 0:
        raise ValueError("Ignore .env in Git before HTTPS setup.")
    result = subprocess.run(
        ["docker", "compose", "--profile", "https", "config", "--format", "json"],
        cwd=ROOT, capture_output=True, text=True,
    )
    if result.returncode != 0:
        # Compose parse errors can quote private configuration. Do not echo them.
        raise ValueError("Compose configuration is invalid; inspect .env privately.")
    settings = json.loads(result.stdout)["services"]["https_php"]["environment"]
    key = settings.get("APP_CSRF_MAC_KEY", "")
    if not isinstance(key, str) or not re.fullmatch(r"(?:[0-9a-f]{2}){32,}", key):
        raise ValueError("Configure a valid independent APP_CSRF_MAC_KEY before HTTPS setup.")
    return settings


def local_origin_content():
    """Prepare an origin-only edit, rejecting syntax this editor cannot preserve."""
    assignment = re.compile(r"^[ \t]*(?:export[ \t]+)?([A-Za-z_][A-Za-z0-9_]*)[ \t]*=[ \t]*(.*)$")
    quoted = re.compile(
        r"""(?:'(?:[^'\\\r\n]|\\[^\r\n])*'|"(?:[^"\\\r\n]|\\[^\r\n])*")[ \t]*(?:#.*)?"""
    )
    lines = []
    # Split only on LF, not Unicode separators that may be literal value content.
    # Keep original CRLF bytes, spacing and comments on every unrelated line.
    for line in ENV.read_bytes().decode("utf-8").split("\n"):
        stripped = line.removesuffix("\r").strip(" \t")
        if not stripped or stripped.startswith("#"):
            lines.append(line)
            continue
        match = assignment.fullmatch(line.removesuffix("\r"))
        value = match[2].lstrip() if match else ""
        if match is None or (
            value.startswith(("'", '"')) and not quoted.fullmatch(value)
        ):
            raise ValueError(
                "HTTPS setup supports only single-line .env assignments and comments; "
                "inspect .env privately."
            )
        if match[1] != "APP_BROWSER_ORIGIN":
            lines.append(line)
    content = "\n".join(lines)
    if content and not content.endswith("\n"):
        content += "\n"
    return content + f"APP_BROWSER_ORIGIN={ORIGIN}\n"


def configure():
    """Preserve existing settings and key while setting the local HTTPS origin."""
    environment()
    if os.environ.get("APP_BROWSER_ORIGIN", ORIGIN) != ORIGIN:
        raise ValueError("Unset the shell APP_BROWSER_ORIGIN override before local HTTPS setup.")
    content = local_origin_content()
    STATE.mkdir(parents=True, exist_ok=True, mode=0o700)
    temporary = None
    try:
        with tempfile.NamedTemporaryFile(mode="w", dir=STATE, delete=False) as output:
            temporary = Path(output.name)
            output.write(content)
        temporary.replace(ENV)
    finally:
        if temporary is not None:
            temporary.unlink(missing_ok=True)
    print("Updated only APP_BROWSER_ORIGIN in .env; existing key preserved.")


def main():
    command = sys.argv[1] if len(sys.argv) == 2 else ""
    if command == "configure":
        configure()
    elif command in {"preflight", "check"}:
        settings = environment()
        if command == "check" and settings.get("APP_BROWSER_ORIGIN") != ORIGIN:
            raise ValueError("Local HTTPS requires APP_BROWSER_ORIGIN=https://localhost:18443.")
        if command == "preflight":
            if os.environ.get("APP_BROWSER_ORIGIN", ORIGIN) != ORIGIN:
                raise ValueError("Unset the shell APP_BROWSER_ORIGIN override before local HTTPS setup.")
            local_origin_content()
    else:
        raise ValueError("Usage: local_https.py {preflight|configure|check}")


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError, KeyError):
        # Do not stringify unexpected parser/system exceptions: they may carry private data.
        error = sys.exc_info()[1]
        if type(error) is ValueError:
            print(str(error), file=sys.stderr)
        else:
            print("Unable to process local HTTPS configuration safely.", file=sys.stderr)
        sys.exit(1)
