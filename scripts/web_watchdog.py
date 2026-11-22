#!/usr/bin/python3
"""Restarts the settings page if it stops answering.  Run every 30 seconds by car-counter-watchdog.timer (as root).

The counter itself keeps counting whatever the page is doing; this only looks after the page.  Any answer from the page
(even the login prompt) counts as "alive".  Two silent checks in a row (about a minute) restart it, and the restart is
logged:  journalctl -t car-counter-watchdog
"""
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request

ENV_FILE = "/etc/car-counter/car-counter.env"
STATE = "/run/car-counter-webcheck"
WEB = "car-counter-web"


def log(msg):
    try:
        subprocess.run(["logger", "-t", "car-counter-watchdog", msg], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except OSError:
        pass
    print(msg)


def active(unit):
    return subprocess.run(["systemctl", "is-active", "--quiet", unit]).returncode == 0


def port():
    try:
        with open(ENV_FILE) as fh:
            m = re.search(r"^CC_WEB_PORT=(\d+)", fh.read(), re.M)
            return int(m.group(1)) if m else 80
    except OSError:
        return 80


def answers():
    try:
        urllib.request.urlopen("http://127.0.0.1:%d/" % port(), timeout=10).close()
    except urllib.error.HTTPError:
        pass
    except (OSError, ValueError):
        return False
    return True


def main():
    if active("car-counter-update") or not active(WEB):     # an update is running, or systemd is already handling it
        _set(0)
        return 0
    if answers():
        _set(0)
        return 0
    fails = _get() + 1
    if fails < 2:
        _set(fails)
        return 0
    log("The settings page did not answer twice in a row - restarting it.")
    subprocess.run(["systemctl", "restart", WEB])
    _set(0)
    return 0


def _get():
    try:
        with open(STATE) as fh:
            return int(fh.read().strip() or 0)
    except (OSError, ValueError):
        return 0


def _set(n):
    try:
        with open(STATE, "w") as fh:
            fh.write(str(n))
    except OSError:
        pass


if __name__ == "__main__":
    sys.exit(main())
