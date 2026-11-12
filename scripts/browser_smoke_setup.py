#!/usr/bin/env python3
"""Explicitly select the real HTTPS gateway's isolated browser-test upstream."""

import argparse
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import sys

from resource_lifecycle.runtime import selected

ROOT = Path(__file__).resolve().parent.parent


def run(*args):
    result = subprocess.run(args, cwd=ROOT, capture_output=True, text=True, timeout=120)
    if result.returncode:
        raise ValueError("Browser gateway setup failed; inspect the owning prerequisite privately.")
    return result.stdout


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ca", required=True, type=Path, help="Existing public root CA; never a private key")
    parser.add_argument("--native", action="store_true", help="Prepare native CI's direct Nginx envelope")
    args = parser.parse_args()
    ca = args.ca.resolve(strict=True)
    run("openssl", "x509", "-in", str(ca), "-noout")
    if "PRIVATE KEY" in ca.read_text():
        raise ValueError("Only a public CA certificate may enter browser setup.")
    runtime = str(ROOT / "bin/runtime")
    if args.native:
        state = ROOT / "var/browser-smoke"
        state.mkdir(parents=True, exist_ok=True, mode=0o700)
        origin = "https://localhost:18443"
        address = "127.0.0.1"
        configuration = (ROOT / "etc/docker/https/default.conf").read_text().replace("listen 8443 ssl", "listen 18443 ssl")
        configuration = configuration.replace("fastcgi_pass https_php:9000;", "fastcgi_pass 127.0.0.1:19087;")
        configuration = configuration.replace("SCRIPT_FILENAME /app/public/index.php", f"SCRIPT_FILENAME {ROOT}/public/index.php")
        configuration = configuration.replace("DOCUMENT_ROOT /app/public", f"DOCUMENT_ROOT {ROOT}/public")
        gateway = state / "default.conf"
        gateway.write_text(configuration)
        shutil.copyfile(ca, state / "rootCA.pem")
        # The CI setup step starts pinned Nginx separately, before the read-only gate.
        topology = "native-nginx"
        fpm = shutil.which("php-fpm8.5") or shutil.which("php-fpm")
        if not fpm:
            raise ValueError("Explicitly install PHP FPM before browser setup.")
    else:
        safe, _, _, _, _ = selected(ROOT)
        front_door = json.loads((safe / "var/local-ingress/compose.json").read_text())["x-fight-front-door"]
        topology = str(front_door) + "-front-door/shared-ingress/task-nginx"
        config = json.loads(run(runtime, "docker", "compose", "--profile", "https", "config", "--format", "json"))
        services = config["services"]
        origin = services["https"]["environment"].get("VIRTUAL_HOST")
        if not origin or services["https"]["environment"].get("FIGHT_LOCAL_INGRESS") != "1":
            raise ValueError("Prepare this checkout's actual shared HTTPS gateway first.")
        origin = "https://" + origin
        output = next(v["source"] for v in services["web"]["volumes"] if v["target"] == "/app/var")
        state = Path(output) / "browser-smoke"
        state.mkdir(parents=True, exist_ok=True, mode=0o700)
        gateway_dir = next(v["source"] for v in services["https"]["volumes"] if v["target"] == "/etc/nginx/conf.d")
        gateway = Path(gateway_dir) / "default.conf"
        configuration = gateway.read_text()
        preimage = configuration
        old, new = "fastcgi_pass https_php:9000;", "fastcgi_pass web:19087;"
        if configuration.count(old) + configuration.count(new) != 1:
            raise ValueError("Unrecognized gateway upstream; preserve its configuration.")
        configuration = configuration.replace(old, new)
        # Only the task gateway's FastCGI supplier changes. Every TLS, authority,
        # header, static-asset and logging directive remains the owning envelope.
        gateway.write_text(configuration)
        try:
            run(runtime, "docker", "compose", "exec", "-T", "https", "nginx", "-t")
            run(runtime, "docker", "compose", "exec", "-T", "https", "nginx", "-s", "reload")
        except (ValueError, subprocess.SubprocessError):
            if gateway.read_text() == configuration:
                gateway.write_text(preimage)
                run(runtime, "docker", "compose", "exec", "-T", "https", "nginx", "-t")
                run(runtime, "docker", "compose", "exec", "-T", "https", "nginx", "-s", "reload")
            raise
        address = run(runtime, "docker", "compose", "exec", "-T", "web", "getent", "ahostsv4", "host.docker.internal").split()[0]
        shutil.copyfile(ca, state / "rootCA.pem")
        fpm = "/usr/local/sbin/php-fpm"
    (state / "envelope.json").write_text(json.dumps({
        "origin": origin,
        "address": address,
        "fpm": fpm,
        "gateway_sha256": hashlib.sha256(configuration.encode()).hexdigest(),
        "ca_sha256": hashlib.sha256(ca.read_bytes()).hexdigest(),
        "topology": topology,
    }, indent=2) + "\n")
    print("Browser gateway prepared: real Nginx, isolated per-run FPM; no development database fallback.")
    print("Task URL serves test fixtures only during smoke. Ordinary bin/up restores its development upstream.")


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError, KeyError, StopIteration, subprocess.SubprocessError):
        print("Browser gateway preparation incomplete; no private configuration emitted.", file=sys.stderr)
        sys.exit(1)
