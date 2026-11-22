#!/usr/bin/python3
"""Installs a new version of the counter from GitHub.  Runs as root; the web page may only run  `updater.py apply`  (one sudo rule).

    updater.py apply   start an update in the background (its own systemd unit, so restarting the web page cannot stop it)
    updater.py run     the update itself (started by "apply"; not meant to be run by hand)

What "run" does, and why it is safe to press the button:
  1. asks GitHub for the newest VERSION and stops if you already have it (it never installs an older version);
  2. downloads that version from the one fixed repository below (HTTPS, size limited) and unpacks it carefully;
  3. checks it: the expected files are there and the code at least compiles;
  4. keeps a copy of the working version in /opt/car-counter.prev;
  5. runs the new version's installer in update mode (keeps your settings, counts, password and port);
  6. waits until the counter and the web page both answer.  If anything fails, it puts the old version back.
Your counts and settings (/var/lib/car-counter) are never touched.  Progress is written to RESULT for the web page to show.

Standard library only (Python 3.7+).
"""
import json
import os
import py_compile
import re
import shutil
import subprocess
import sys
import tarfile
import time
import urllib.error
import urllib.request

REPO = "baelinc/christmas_lidar_counter"
TARBALL_URL = "https://codeload.github.com/%s/tar.gz/refs/heads/main" % REPO
VERSION_URL = "https://raw.githubusercontent.com/%s/main/VERSION" % REPO
APP = "/opt/car-counter"
RUN_DIR = "/run/car-counter"
WORK = "/var/lib/car-counter-update"
ENV_FILE = "/etc/car-counter/car-counter.env"
UNIT = "car-counter-update"
SERVICES = ("car-counter", "car-counter-web")
MAX_TARBALL = 25 << 20
MAX_UNPACKED = 80 << 20
HEALTH_WAIT = 90               # seconds to wait for the new version to answer
FRESH = 900                    # an update that has not reported for this long is considered dead
STEPS = 6
UA = "christmas-lidar-counter-updater"


def result_path():
    return os.path.join(RUN_DIR, "update_result.json")


def write_result(state, message, step=0, **extra):
    data = {"state": state, "message": message, "step": step, "steps": STEPS, "ts": time.time()}
    data.update(extra)
    tmp = result_path() + ".tmp"
    try:
        with open(tmp, "w") as fh:
            json.dump(data, fh)
        os.chmod(tmp, 0o644)
        os.replace(tmp, result_path())
    except OSError:
        pass
    return data


def read_result():
    try:
        with open(result_path()) as fh:
            r = json.load(fh)
        return r if isinstance(r, dict) else None
    except (OSError, ValueError):
        return None


# ---------------------------------------------------------------- versions
def parse_version(text):
    first = (text or "").strip().splitlines()[0] if (text or "").strip() else ""
    m = re.match(r"^\s*v?(\d+(?:\.\d+){0,3})\s*$", first)
    return tuple(int(x) for x in m.group(1).split(".")) if m else None


def newer(a, b):
    x, y = parse_version(a), parse_version(b)
    if x is None or y is None:
        return False
    n = max(len(x), len(y))
    return x + (0,) * (n - len(x)) > y + (0,) * (n - len(y))


def installed_version():
    try:
        with open(os.path.join(APP, "VERSION")) as fh:
            v = fh.read().strip()
            if parse_version(v):
                return v
    except OSError:
        pass
    try:                                                   # an install from before the VERSION file existed
        with open(os.path.join(APP, "counter", "car_counter.py")) as fh:
            m = re.search(r'^VERSION\s*=\s*"([^"]+)"', fh.read(), re.M)
            return m.group(1) if m else ""
    except OSError:
        return ""


def http_get(url, limit):
    req = urllib.request.Request(url, headers={"User-Agent": UA, "Cache-Control": "no-cache"})
    with urllib.request.urlopen(req, timeout=60) as resp:
        data = resp.read(limit + 1)
    if len(data) > limit:
        raise ValueError("The download was larger than expected, so it was not used.")
    return data


# ---------------------------------------------------------------- unpacking and checking
def safe_extract(tar_path, dest):
    """Unpack a GitHub tarball without trusting it: only plain files and folders, nothing outside `dest`, size limited."""
    total = 0
    top = None
    os.makedirs(dest, exist_ok=True)
    root = os.path.realpath(dest)
    with tarfile.open(tar_path, "r:gz") as tf:
        for m in tf.getmembers():
            name = m.name.lstrip("./")
            if not name:
                continue
            parts = name.split("/")
            if name.startswith("/") or ".." in parts:
                raise ValueError("The download contains an unsafe path.")
            if top is None:
                top = parts[0]
            elif parts[0] != top:
                raise ValueError("The download has an unexpected layout.")
            if m.isdir():
                os.makedirs(os.path.join(dest, name), exist_ok=True)
                continue
            if m.name == "pax_global_header" or m.type in (tarfile.XGLTYPE, tarfile.XHDTYPE):
                continue
            if not m.isreg():
                raise ValueError("The download contains something other than plain files.")
            total += m.size
            if total > MAX_UNPACKED:
                raise ValueError("The download unpacks to more than expected.")
            target = os.path.realpath(os.path.join(dest, name))
            if not target.startswith(root + os.sep):
                raise ValueError("The download contains an unsafe path.")
            os.makedirs(os.path.dirname(target), exist_ok=True)
            with tf.extractfile(m) as src, open(target, "wb") as out:
                shutil.copyfileobj(src, out)
            os.chmod(target, 0o755 if (m.mode & 0o111) else 0o644)
    if top is None:
        raise ValueError("The download was empty.")
    return os.path.join(dest, top)


REQUIRED = ("install.sh", "VERSION", "counter/car_counter.py", "web/index.php", "web/router.php", "web/ajax.php",
            "systemd/car-counter.service", "systemd/car-counter-web.service", "scripts/updater.py")


def verify(src, current):
    for rel in REQUIRED:
        if not os.path.isfile(os.path.join(src, rel)):
            raise ValueError("The new version is missing %s." % rel)
    with open(os.path.join(src, "VERSION")) as fh:
        new = fh.read().strip()
    if parse_version(new) is None:
        raise ValueError("The new version has no valid version number.")
    if current and not newer(new, current):
        raise ValueError("The download (%s) is not newer than what you have (%s)." % (new, current))
    with open(os.path.join(src, "install.sh")) as fh:
        if "--update" not in fh.read():
            raise ValueError("The new installer does not support updating.")
    pyfiles = [os.path.join(src, "counter", "car_counter.py")] + sorted(
        os.path.join(src, "scripts", f) for f in os.listdir(os.path.join(src, "scripts")) if f.endswith(".py"))
    for f in pyfiles:
        py_compile.compile(f, cfile=os.path.join(WORK, "check.pyc"), doraise=True)
    if subprocess.run(["bash", "-n", os.path.join(src, "install.sh")], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode != 0:
        raise ValueError("The new installer has a syntax error.")
    if shutil.which("php"):
        webdir = os.path.join(src, "web")
        for f in sorted(os.listdir(webdir)):
            if f.endswith(".php"):
                r = subprocess.run(["php", "-l", os.path.join(webdir, f)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                                   universal_newlines=True, timeout=30)
                if r.returncode != 0:
                    raise ValueError("The new web page (%s) has an error." % f)
    return new


# ---------------------------------------------------------------- the services
def systemctl(*args, timeout=120):
    return subprocess.run(["systemctl"] + list(args), stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                          universal_newlines=True, timeout=timeout)


def web_port():
    try:
        with open(ENV_FILE) as fh:
            m = re.search(r"^CC_WEB_PORT=(\d+)", fh.read(), re.M)
            return int(m.group(1)) if m else 80
    except OSError:
        return 80


def healthy():
    """Both the counter (fresh status file) and the web page (any HTTP answer, even a login prompt) respond."""
    try:
        with open(os.path.join(RUN_DIR, "status.json")) as fh:
            if time.time() - float(json.load(fh).get("ts", 0)) > 10:
                return False
    except (OSError, ValueError):
        return False
    try:
        urllib.request.urlopen("http://127.0.0.1:%d/" % web_port(), timeout=5).close()
    except urllib.error.HTTPError:
        pass                                              # 401 = the login page is answering
    except (OSError, ValueError):
        return False
    return True


def wait_healthy():
    end = time.time() + HEALTH_WAIT
    while time.time() < end:
        if healthy():
            return True
        time.sleep(2)
    return False


def run_installer(src):
    log = os.path.join(WORK, "install.log")
    with open(log, "w") as fh:
        r = subprocess.run(["bash", os.path.join(src, "install.sh"), "--update"], cwd=src, stdout=fh, stderr=subprocess.STDOUT, timeout=900)
    return r.returncode, log


def tail(path, n=6):
    try:
        with open(path, errors="replace") as fh:
            return " | ".join(l.strip() for l in fh.read().strip().splitlines()[-n:])
    except OSError:
        return ""


def put_back_old_version():
    """Restore /opt/car-counter from the copy made before the update and restart the services."""
    prev = APP + ".prev"
    if not os.path.isdir(prev):
        return False
    shutil.rmtree(APP, ignore_errors=True)
    os.rename(prev, APP)
    for u in SERVICES:                                    # the old unit files, written the same way install.sh does
        src = os.path.join(APP, "systemd", u + ".service")
        if os.path.isfile(src):
            with open(src) as fh:
                text = fh.read().replace("@APP@", APP)
            with open("/etc/systemd/system/%s.service" % u, "w") as out:
                out.write(text)
    systemctl("daemon-reload")
    systemctl("restart", *SERVICES)
    return True


# ---------------------------------------------------------------- the update
def run():
    current = installed_version()
    touched = False                                       # becomes True once we start changing the installed program
    try:
        write_result("checking", "Asking GitHub for the newest version…", 1, **{"from": current})
        latest = http_get(VERSION_URL, 256).decode("utf-8", "replace").strip()
        if parse_version(latest) is None:
            raise ValueError("GitHub returned something that is not a version number.")
        if not newer(latest, current):
            write_result("uptodate", "You already have the latest version (%s)." % current, STEPS, **{"from": current, "to": current})
            return 0

        write_result("downloading", "Downloading version %s…" % latest, 2, **{"from": current, "to": latest})
        shutil.rmtree(WORK, ignore_errors=True)
        os.makedirs(WORK, mode=0o700, exist_ok=True)
        tar = os.path.join(WORK, "update.tar.gz")
        with open(tar, "wb") as fh:
            fh.write(http_get(TARBALL_URL, MAX_TARBALL))

        write_result("verifying", "Checking the download…", 3, **{"from": current, "to": latest})
        src = safe_extract(tar, os.path.join(WORK, "src"))
        new = verify(src, current)

        write_result("installing", "Keeping a copy of version %s, then installing %s…" % (current or "?", new), 4, **{"from": current, "to": new})
        prev = APP + ".prev"
        shutil.rmtree(prev, ignore_errors=True)
        if os.path.isdir(APP):
            shutil.copytree(APP, prev, symlinks=True)
        write_result("installing", "Installing version %s…" % new, 5, **{"from": current, "to": new})
        touched = True
        rc, log = run_installer(src)
        if rc != 0:
            raise RuntimeError("The installer stopped (%s)" % (tail(log) or "exit code %d" % rc))

        write_result("restarting", "Restarting and checking that it works…", 6, **{"from": current, "to": new})
        if not wait_healthy():
            raise RuntimeError("The new version did not start properly")
        write_result("ok", "Updated from %s to %s." % (current or "an older version", new), STEPS, **{"from": current, "to": new})
        shutil.rmtree(WORK, ignore_errors=True)
        return 0
    except Exception as exc:                              # anything at all: report it, and undo what we did
        msg = str(exc) or exc.__class__.__name__
        if isinstance(exc, (urllib.error.URLError, OSError)) and not isinstance(exc, RuntimeError):
            msg = "Could not download the update (%s)." % (getattr(exc, "reason", None) or exc)
        restored = False
        try:
            if touched:
                restored = put_back_old_version()
        except Exception:
            restored = False
        write_result("failed", msg + (". The previous version was put back and is running again." if restored else ". Nothing was changed."),
                     STEPS, rolled_back=restored, **{"from": current})
        return 1


def apply():
    cur = read_result()
    if cur and cur.get("state") in ("starting", "checking", "downloading", "verifying", "installing", "restarting") \
            and time.time() - float(cur.get("ts", 0)) < FRESH:
        print(json.dumps({"ok": False, "error": "An update is already running."}))
        return 0
    write_result("starting", "Starting the update…", 0, **{"from": installed_version()})
    script = os.path.join(APP, "scripts", "updater.py")
    launched = False
    if shutil.which("systemd-run"):
        r = subprocess.run(["systemd-run", "--unit", UNIT, "--collect", "--quiet", script, "run"],
                           stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, universal_newlines=True)
        launched = r.returncode == 0
        if not launched and "already" in (r.stderr or "").lower():
            print(json.dumps({"ok": False, "error": "An update is already running."}))
            return 0
    if not launched:                                      # no systemd-run: detach ourselves as best we can
        subprocess.Popen([script, "run"], stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)
    print(json.dumps({"ok": True, "started": True}))
    return 0


def main(argv):
    if len(argv) == 2 and argv[1] == "apply":
        return apply()
    if len(argv) == 2 and argv[1] == "run" and os.geteuid() == 0:
        return run()
    print(json.dumps({"ok": False, "error": "usage: updater.py apply"}))
    return 2


if __name__ == "__main__":
    sys.exit(main(sys.argv))
