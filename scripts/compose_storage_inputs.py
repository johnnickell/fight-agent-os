#!/usr/bin/env python3
"""Resolve bin/up's selected Compose image inputs before storage admission (no effects)"""

import json
from pathlib import Path
import re
import shlex
import subprocess
import sys


class Unavailable(Exception):
    """Refuse admission when current inputs cannot be established"""


def dockerfile_inputs(source, arguments):
    """Collect external FROM inputs; unresolved expressions refuse reclamation"""
    variables, stages, images = {}, set(), []
    header = True
    source = re.sub(r"\\\r?\n", " ", source)
    for line in source.splitlines():
        words = shlex.split(line, comments=True)
        if not words:
            continue
        instruction = words.pop(0).upper()
        if instruction == "ARG" and header and words:
            name, _, default = words[0].partition("=")
            variables[name] = arguments.get(name, default)
        if instruction != "FROM":
            continue
        header = False
        while words and words[0].startswith("--"):
            words.pop(0)
        if not words:
            raise Unavailable()
        def expand(match):
            value = variables.get(match[1] or match[2])
            if not isinstance(value, str) or not value:
                raise Unavailable()
            return value
        image = re.sub(r"\$\{([A-Za-z_][A-Za-z0-9_]*)\}|\$([A-Za-z_][A-Za-z0-9_]*)", expand, words[0])
        if image.lower() not in stages and image.lower() != "scratch":
            images.append(image)
        if len(words) == 3 and words[1].upper() == "AS":
            stages.add(words[2].lower())
    return images


def resolve(build_only):
    command = ["docker", "compose", *([] if build_only else ["--profile", "https"]), "config", "--format", "json"]
    result = subprocess.run(command, capture_output=True, timeout=30)
    if result.returncode or len(result.stdout) > 8 * 1024 * 1024:
        raise Unavailable()
    return inputs(json.loads(result.stdout), Path.cwd())


def inputs(model, root):
    """Resolve the owning effective model, not ambient Compose defaults"""
    services = model["services"]
    if not isinstance(services, dict) or not services:
        raise Unavailable()
    images = []
    # Keep the repository's fixed build inputs protected as well as selected overrides.
    for path in ("etc/docker/cli/Dockerfile", "etc/docker/fpm/Dockerfile"):
        images.extend(dockerfile_inputs((root / path).read_text(), {}))
    for name, service in services.items():
        if service.get("image"):
            images.append(service["image"])
        build = service.get("build")
        if not build:
            continue
        if not service.get("image"):
            images.append(model["name"] + "-" + name)
        context = Path(build["context"])
        if not context.is_absolute() or not context.is_dir() or build.get("additional_contexts"):
            raise Unavailable()
        source = build.get("dockerfile_inline")
        if source is None:
            path = Path(build.get("dockerfile", "Dockerfile"))
            source = (path if path.is_absolute() else context / path).read_text()
        images.extend(dockerfile_inputs(source, build.get("args", {})))
    images = sorted(set(images))
    if not images or len(images) > 64 or any(not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_.:/@-]{0,511}", image) for image in images):
        raise Unavailable()
    return images


if __name__ == "__main__":
    try:
        if sys.argv[1:] not in ([], ["--build-only"]):
            raise Unavailable()
        print("\n".join(resolve(bool(sys.argv[1:]))))
    except (Unavailable, OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError):
        print("Compose/build image inputs unavailable or unsupported; storage admission refused before effects. Correct the selected Compose configuration/build inputs, then rerun ./bin/up.", file=sys.stderr)
        sys.exit(1)
