#!/usr/bin/env python3
"""Explicitly select the real HTTPS gateway's isolated browser-test upstream."""

import argparse
import hashlib
import json
import os
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


def direct_state(config):
    output = next(v["source"] for v in config["services"]["web"]["volumes"] if v["target"] == "/app/var")
    return Path(output) / "browser-smoke"


def remove_direct(runtime):
    config = json.loads(run(runtime, "docker", "compose", "--profile", "https", "config", "--format", "json"))
    name = config["name"] + "-browser-https"
    ids = run(runtime, "docker", "ps", "-aq", "--filter", "name=^/" + name + "$").split()
    state = direct_state(config)
    if ids:
        container = json.loads(run(runtime, "docker", "inspect", ids[0]))[0]
        labels = container["Config"]["Labels"]
        if (labels.get("com.docker.compose.project") != config["name"] or
                labels.get("com.docker.compose.service") != "browser_https" or
                not any(m["Source"] == str(state / "direct.conf") and
                        m["Destination"] == "/etc/nginx/conf.d/default.conf" for m in container["Mounts"])):
            raise ValueError("Preserve unknown gateway ownership.")
        run(runtime, "docker", "stop", "--time", "10", ids[0])
        run(runtime, "docker", "rm", ids[0])
    envelope = state / "envelope.json"
    if envelope.exists() and json.loads(envelope.read_text())["topology"] == "private-direct-nginx":
        envelope.unlink()
        (state / "rootCA.pem").unlink(missing_ok=True)
    (state / "direct.conf").unlink(missing_ok=True)
    print("Private direct browser gateway removed; ordinary HTTPS and all databases preserved.")


def prepare_direct(runtime, config, ca, cert_dir):
    services = config["services"]
    state = direct_state(config)
    name = config["name"] + "-browser-https"
    if run(runtime, "docker", "ps", "-aq", "--filter", "name=^/" + name + "$").strip():
        raise ValueError("Remove the existing private browser gateway explicitly before preparation.")
    if cert_dir is None:
        cert_dir = Path(next(v["source"] for v in services["https"]["volumes"] if v["target"] == "/etc/nginx/certs"))
    cert_dir = cert_dir.resolve(strict=True)
    # Qualify the existing leaf against the selected public CA; never enroll trust or generate keys.
    run(os.environ.get("FIGHT_INGRESS_OPENSSL", "openssl"), "verify", "-CAfile", str(ca),
        "-verify_hostname", "localhost", str(cert_dir / "localhost.pem"))
    configuration = (ROOT / "etc/docker/https/default.conf").read_text().replace("listen 8443 ssl", "listen 18443 ssl")
    configuration = configuration.replace("fastcgi_pass https_php:9000;", "fastcgi_pass web:19087;")
    state.mkdir(parents=True, exist_ok=True, mode=0o700)
    gateway = state / "direct.conf"
    gateway.write_text(configuration)
    assets = next(v["source"] for v in services["https"]["volumes"] if v["target"] == "/app/var/client/build")
    network = config["networks"]["default"]["name"]
    # Same selected project/private network, no published port, development upstream or shared ingress.
    run(runtime, "docker", "run", "--detach", "--pull", "never", "--name", name,
        "--label", "com.docker.compose.project=" + config["name"],
        "--label", "com.docker.compose.service=browser_https", "--network", network,
        "--read-only", "--tmpfs", "/var/cache/nginx", "--tmpfs", "/var/run", "--tmpfs", "/tmp",
        "--memory", "128m", "--cpus", "0.5", "--pids-limit", "64",
        "--security-opt", "no-new-privileges:true",
        "--mount", f"type=bind,source={gateway},target=/etc/nginx/conf.d/default.conf,readonly",
        "--mount", f"type=bind,source={cert_dir / 'localhost.pem'},target=/etc/nginx/certs/localhost.pem,readonly",
        "--mount", f"type=bind,source={cert_dir / 'localhost-key.pem'},target=/etc/nginx/certs/localhost-key.pem,readonly",
        "--mount", f"type=bind,source={assets},target=/app/var/client/build,readonly",
        "--entrypoint", "nginx", services["https"]["image"], "-g", "daemon off;")
    run(runtime, "docker", "exec", name, "nginx", "-t")
    container = json.loads(run(runtime, "docker", "inspect", name))[0]
    address = container["NetworkSettings"]["Networks"][network]["IPAddress"]
    if not container["State"]["Running"] or not address:
        raise ValueError("Private direct gateway did not start; inspect and remove the owned fixture.")
    shutil.copyfile(ca, state / "rootCA.pem")
    (state / "envelope.json").write_text(json.dumps({
        "origin": "https://localhost:18443", "address": address, "fpm": "/usr/local/sbin/php-fpm",
        "gateway_sha256": hashlib.sha256(configuration.encode()).hexdigest(),
        "ca_sha256": hashlib.sha256(ca.read_bytes()).hexdigest(), "topology": "private-direct-nginx",
    }, indent=2) + "\n")
    print("Private direct browser gateway prepared at https://localhost:18443 (browser-only mapping).")
    print("Ordinary main gateway/data unchanged; explicit --remove-direct retires only this fixture.")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ca", type=Path, help="Existing public root CA; never a private key")
    parser.add_argument("--native", action="store_true", help="Prepare native CI's direct Nginx envelope")
    parser.add_argument("--direct", action="store_true", help="Prepare a private direct localhost Nginx, without shared ingress")
    parser.add_argument("--cert-dir", type=Path, help="Direct-only existing localhost.pem/localhost-key.pem directory")
    parser.add_argument("--remove-direct", action="store_true", help="Remove only this project's private direct gateway")
    args = parser.parse_args()
    if args.native and (args.direct or args.remove_direct or args.cert_dir):
        raise ValueError("Select one preparation topology.")
    runtime = str(ROOT / "bin/runtime")
    if args.remove_direct:
        remove_direct(runtime)
        return
    if not args.ca:
        raise ValueError("An existing public CA is required.")
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
        config = json.loads(run(runtime, "docker", "compose", "--profile", "https", "config", "--format", "json"))
        services = config["services"]
        shared = services["https"].get("environment", {}).get("FIGHT_LOCAL_INGRESS") == "1"
        if args.direct or not shared:
            prepare_direct(runtime, config, ca, args.cert_dir)
            return
        if args.cert_dir:
            raise ValueError("Certificate directory is direct-only.")
        front_door = json.loads((safe / "var/local-ingress/compose.json").read_text())["x-fight-front-door"]
        topology = str(front_door) + "-front-door/shared-ingress/task-nginx"
        origin = "https://" + services["https"]["environment"]["VIRTUAL_HOST"]
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
