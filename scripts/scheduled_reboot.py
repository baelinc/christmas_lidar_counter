#!/usr/bin/python3
"""Restarts the Pi on the schedule chosen on the web page (Updates & Restart tab).  Run every 30 seconds by
car-counter-watchdog.timer, as root, so it needs no special permission.  Reads /var/lib/car-counter/config.json.

Safety rules:
  * only acts within 10 minutes after the chosen time, so saving the setting never causes an instant restart;
  * a Pi that booted after today's chosen time is left alone, so it can never restart twice or loop;
  * never restarts while an update is running, or in the first 15 minutes after start-up.
See what it did:  journalctl -t car-counter-reboot
"""
import datetime
import json
import os
import re
import subprocess
import sys

DATA = os.environ.get("CC_DATA_DIR", "/var/lib/car-counter")
WINDOW_MIN = 10
MIN_UPTIME = 15 * 60
DAYS = ["mon", "tue", "wed", "thu", "fri", "sat", "sun"]
GAP = {"daily": 1, "every_other_day": 2, "weekly": 7}


def log(msg):
    subprocess.run(["logger", "-t", "car-counter-reboot", msg], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    print(msg)


def uptime_seconds():
    with open("/proc/uptime") as fh:
        return float(fh.read().split()[0])


def should_reboot(cfg, now, uptime):
    """True if a scheduled restart is due.  `now` is local time (naive), `uptime` in seconds."""
    mode = cfg.get("reboot_schedule", "off")
    if mode not in GAP:
        return False
    m = re.match(r"^(\d{1,2}):(\d{2})$", str(cfg.get("reboot_time", "03:00")))
    if not m or int(m.group(1)) > 23 or int(m.group(2)) > 59:
        return False
    due = now.replace(hour=int(m.group(1)), minute=int(m.group(2)), second=0, microsecond=0)
    if not (due <= now < due + datetime.timedelta(minutes=WINDOW_MIN)):
        return False
    if uptime < MIN_UPTIME:
        return False
    if mode == "weekly" and DAYS[now.weekday()] != cfg.get("reboot_weekday", "sun"):
        return False
    booted = now - datetime.timedelta(seconds=uptime)
    if booted >= due:
        return False                                       # already restarted since the chosen time
    if mode == "every_other_day" and (now.date() - booted.date()).days < GAP[mode]:
        return False
    return True


def update_running():
    return subprocess.run(["systemctl", "is-active", "--quiet", "car-counter-update"]).returncode == 0


def main():
    try:
        with open(os.path.join(DATA, "config.json")) as fh:
            cfg = json.load(fh)
    except (OSError, ValueError):
        return 0
    if not isinstance(cfg, dict) or cfg.get("reboot_schedule", "off") == "off":
        return 0
    if not should_reboot(cfg, datetime.datetime.now(), uptime_seconds()) or update_running():
        return 0
    log("Scheduled restart (%s at %s) - restarting the Pi now." % (cfg.get("reboot_schedule"), cfg.get("reboot_time")))
    subprocess.run(["systemctl", "reboot"])
    return 0


if __name__ == "__main__":
    sys.exit(main())
