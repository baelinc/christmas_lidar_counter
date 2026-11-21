#!/usr/bin/python3
"""Wi-Fi helper for the Christmas Lidar Counter web page.

Run as root through a narrow sudo rule (see install.sh); the web page never gets a shell.
Talks to NetworkManager through `nmcli` and prints one JSON object.

    wifi_helper.py status
    wifi_helper.py scan yes|no          (yes = look again now, no = use the list NetworkManager already has)
    wifi_helper.py power on|off
    wifi_helper.py connect              (JSON on stdin: ssid, password, hidden, security, use_saved)
    wifi_helper.py forget               (JSON on stdin: ssid)

Connecting can move the Pi to another network, which may cut the web page's own connection, so
"connect" answers straight away and finishes in the background; the outcome is written to
RESULT_FILE and shown by the page when it can reach the Pi again.

Standard library only (Python 3.7+).
"""
import json
import os
import re
import shutil
import subprocess
import sys
import time

RESULT_FILE = "/run/car-counter/wifi_result.json"
NMCLI = shutil.which("nmcli")
# Test hooks.  Ignored under sudo (which also strips the environment), so the web page cannot redirect them.
if "SUDO_UID" not in os.environ:
    NMCLI = os.environ.get("CC_NMCLI", NMCLI)
    RESULT_FILE = os.environ.get("CC_WIFI_RESULT", RESULT_FILE)

CONNECT_WAIT = 45          # seconds nmcli may take to join a network
RESULT_STALE = 150         # a "working" result older than this is treated as failed
OK_SECURITY = ("open", "wpa-psk", "sae")


# ---------------------------------------------------------------- nmcli plumbing
def run(args, timeout=30):
    """Run nmcli (never through a shell). Returns (exit code, stdout, stderr)."""
    env = dict(os.environ, LC_ALL="C", LANG="C")
    try:
        p = subprocess.run([NMCLI] + list(args), stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                           universal_newlines=True, timeout=timeout, env=env)
        return p.returncode, p.stdout, p.stderr
    except subprocess.TimeoutExpired:
        return 124, "", "timed out"
    except OSError as e:
        return 127, "", str(e)


def split_terse(line):
    """Split one line of `nmcli -t` output on unescaped colons and undo the \\: and \\\\ escapes."""
    out, cur, i = [], [], 0
    while i < len(line):
        c = line[i]
        if c == "\\" and i + 1 < len(line):
            cur.append(line[i + 1]); i += 2; continue
        if c == ":":
            out.append("".join(cur)); cur = []; i += 1; continue
        cur.append(c); i += 1
    out.append("".join(cur))
    return out


def rows(text):
    return [split_terse(l) for l in text.splitlines() if l.strip()]


def fields(text):
    """`nmcli -t -f A,B show ...` prints FIELD:value lines; return {field: value}."""
    d = {}
    for r in rows(text):
        if len(r) >= 2:
            d[r[0]] = ":".join(r[1:])
    return d


def emit(obj, code=0):
    sys.stdout.write(json.dumps(obj))
    sys.stdout.flush()
    return code


def classify(sec):
    """Turn nmcli's SECURITY text into one of: open, wpa-psk, sae, wep, enterprise, owe, unknown."""
    s = (sec or "").strip()
    if s in ("", "--"):
        return "open"
    if "802.1X" in s:
        return "enterprise"
    if "OWE" in s:
        return "owe"
    if "WPA" not in s:
        return "wep" if "WEP" in s else "unknown"
    words = s.split()
    if "WPA3" in words and "WPA2" not in words and "WPA1" not in words and "WPA" not in words:
        return "sae"
    return "wpa-psk"


def scrub(text, secret):
    if secret:
        text = text.replace(secret, "***")
    return text


# ---------------------------------------------------------------- state
def wifi_device():
    rc, out, _ = run(["-t", "-f", "DEVICE,TYPE,STATE,CONNECTION", "device", "status"])
    dev, eth = None, False
    for r in rows(out) if rc == 0 else []:
        if len(r) < 3:
            continue
        if r[1] == "wifi" and dev is None:
            dev = {"name": r[0], "state": r[2], "connection": r[3] if len(r) > 3 else ""}
        if r[1] == "ethernet" and r[2].startswith("connected"):
            eth = True
    return dev, eth


def device_info(name):
    rc, out, _ = run(["-t", "-f", "GENERAL.CONNECTION,GENERAL.CON-UUID,IP4.ADDRESS", "device", "show", name])
    ip, uuid, cidr = "", "", ""
    if rc == 0:
        for k, v in fields(out).items():
            if k.startswith("IP4.ADDRESS") and not ip:
                ip, cidr = v.split("/")[0], v
            if k == "GENERAL.CON-UUID":
                uuid = v
    return ip, uuid, cidr


def read_result():
    try:
        with open(RESULT_FILE) as fh:
            r = json.load(fh)
    except (OSError, ValueError):
        return None
    if not isinstance(r, dict):
        return None
    if r.get("state") == "working" and time.time() - float(r.get("ts", 0)) > RESULT_STALE:
        r = dict(r, state="failed", message="The connection attempt did not finish. Try again.")
    return r


def write_result(state, ssid, message="", ip=""):
    data = {"state": state, "ssid": ssid, "message": message, "ip": ip, "ts": time.time()}
    tmp = RESULT_FILE + ".tmp"
    try:
        with open(tmp, "w") as fh:
            json.dump(data, fh)
        os.chmod(tmp, 0o644)
        os.replace(tmp, RESULT_FILE)
    except OSError:
        pass


def saved_profiles():
    """[{uuid, name, ssid}] for every saved Wi-Fi profile."""
    rc, out, _ = run(["-t", "-f", "NAME,UUID,TYPE", "connection", "show"])
    res = []
    for r in rows(out) if rc == 0 else []:
        if len(r) >= 3 and r[2] == "802-11-wireless":
            rc2, o2, _ = run(["-t", "-f", "802-11-wireless.ssid", "connection", "show", "uuid", r[1]])
            ssid = fields(o2).get("802-11-wireless.ssid", "") if rc2 == 0 else ""
            res.append({"uuid": r[1], "name": r[0], "ssid": ssid or r[0]})
        if len(res) >= 40:
            break
    return res


def scan_list(dev, rescan):
    rc, out, err = run(["-t", "-f", "IN-USE,SSID,SIGNAL,SECURITY,CHAN", "device", "wifi", "list",
                        "ifname", dev, "--rescan", "yes" if rescan else "no"], timeout=45)
    if rc != 0:
        return None, (err.strip() or "Scan failed")
    best = {}
    for r in rows(out):
        if len(r) < 5 or not r[1]:
            continue                                   # hidden networks have no name
        ssid, sig = r[1], int(r[2]) if r[2].isdigit() else 0
        kind = classify(r[3])
        cur = best.get(ssid)
        if cur is None:
            best[ssid] = {"ssid": ssid, "signal": sig, "security": kind, "secure": kind != "open",
                          "channel": r[4], "in_use": r[0].strip() == "*", "supported": kind in OK_SECURITY}
        else:
            cur["in_use"] = cur["in_use"] or r[0].strip() == "*"
            if sig > cur["signal"]:
                cur.update(signal=sig, channel=r[4], security=kind, secure=kind != "open", supported=kind in OK_SECURITY)
    nets = sorted(best.values(), key=lambda n: (not n["in_use"], -n["signal"], n["ssid"].lower()))
    return nets, ""


# ---------------------------------------------------------------- commands
def cmd_status():
    if not NMCLI:
        return emit({"ok": True, "available": False, "reason": "nmcli_missing"})
    rc, out, _ = run(["-t", "-f", "RUNNING", "general"])
    if rc != 0 or out.strip() != "running":
        return emit({"ok": True, "available": False, "reason": "nm_not_running"})
    dev, eth = wifi_device()
    res = {"ok": True, "available": True, "has_device": dev is not None, "ethernet": eth, "result": read_result()}
    if dev is None:
        return emit(res)
    rc, out, _ = run(["radio", "wifi"])
    on = rc == 0 and out.strip() == "enabled"
    res.update(device=dev["name"], radio_on=on, state=dev["state"], ssid="", signal=0, ip="", cidr="")
    if on and dev["state"].startswith("connected"):
        res["ip"], _, res["cidr"] = device_info(dev["name"])
        nets, _ = scan_list(dev["name"], False)
        for n in nets or []:
            if n["in_use"]:
                res["ssid"], res["signal"] = n["ssid"], n["signal"]
        if not res["ssid"]:
            res["ssid"] = dev["connection"]
    return emit(res)


def cmd_scan(rescan):
    if not NMCLI:
        return emit({"ok": False, "error": "NetworkManager (nmcli) is not installed."})
    dev, _ = wifi_device()
    if dev is None:
        return emit({"ok": False, "error": "No Wi-Fi adapter was found."})
    rc, out, _ = run(["radio", "wifi"])
    if rc != 0 or out.strip() != "enabled":
        return emit({"ok": False, "error": "Wi-Fi is turned off.", "radio_on": False})
    nets, err = scan_list(dev["name"], rescan)
    if nets is None:
        return emit({"ok": False, "error": err})
    saved = {p["ssid"] for p in saved_profiles()}
    for n in nets:
        n["saved"] = n["ssid"] in saved
    # saved networks that are not in range right now, so they can still be forgotten
    seen = {n["ssid"] for n in nets}
    return emit({"ok": True, "networks": nets, "saved_out_of_range": sorted(s for s in saved if s and s not in seen)})


def cmd_power(arg):
    if arg not in ("on", "off"):
        return emit({"ok": False, "error": "Say on or off."}, 2)
    if not NMCLI:
        return emit({"ok": False, "error": "NetworkManager (nmcli) is not installed."})
    rc, _, err = run(["radio", "wifi", arg], timeout=20)
    if rc != 0:
        return emit({"ok": False, "error": err.strip() or "Could not change the Wi-Fi switch."})
    if arg == "on":
        time.sleep(2)                                   # let the adapter come up
    return emit({"ok": True, "radio_on": arg == "on"})


def read_payload():
    try:
        d = json.loads(sys.stdin.read() or "{}")
    except ValueError:
        return None
    return d if isinstance(d, dict) else None


def bad_ssid(ssid):
    return (not isinstance(ssid, str) or not ssid or len(ssid.encode("utf-8")) > 32
            or any(c in ssid for c in "\x00\r\n"))


def bad_password(pw, kind):
    if kind == "open":
        return None
    if not isinstance(pw, str) or any(c in pw for c in "\x00\r\n"):
        return "That password has characters that are not allowed."
    if kind == "wpa-psk" or kind == "sae":
        if kind == "wpa-psk" and re.fullmatch(r"[0-9A-Fa-f]{64}", pw):
            return None
        if not 8 <= len(pw) <= 63:
            return "A Wi-Fi password must be 8 to 63 characters."
    return None


def friendly_error(err, ssid):
    e = (err or "").lower()
    if "secrets were required" in e or "no secrets" in e or "802-1x supplicant" in e or "wrong" in e:
        return "The password was not accepted."
    if "no network with ssid" in e or "not found" in e and "ssid" in e:
        return "That network is not in range right now."
    if "timeout" in e or "timed out" in e:
        return "The network did not answer in time. Check the password and move closer, then try again."
    if "ip configuration" in e or "dhcp" in e:
        return "Joined the network but did not get an address from the router."
    msg = (err or "").strip().splitlines()[-1] if (err or "").strip() else ""
    return ("Could not connect: " + msg[:140]) if msg else "Could not connect. Check the password and try again."


def finish_connect(ssid, password, hidden, kind, use_saved, dev):
    _, prev_uuid, _ = device_info(dev)
    profiles = saved_profiles()
    new_uuid = ""
    if use_saved:
        match = [p for p in profiles if p["ssid"] == ssid]
        if not match:
            write_result("failed", ssid, "That network is not saved. Enter its password to connect.")
            return
        target = match[0]["uuid"]
    else:
        args = ["connection", "add", "type", "wifi", "con-name", ssid, "ifname", dev, "ssid", ssid,
                "connection.autoconnect", "yes"]
        if hidden:
            args += ["wifi.hidden", "yes"]
        if kind != "open":
            args += ["wifi-sec.key-mgmt", kind, "wifi-sec.psk", password]
        rc, out, err = run(args, timeout=20)
        m = re.search(r"\(([0-9a-fA-F-]{36})\)", out)
        if rc != 0 or not m:
            write_result("failed", ssid, scrub(friendly_error(err, ssid), password))
            return
        target = new_uuid = m.group(1)
    rc, out, err = run(["-w", str(CONNECT_WAIT), "connection", "up", "uuid", target, "ifname", dev], timeout=CONNECT_WAIT + 15)
    if rc != 0:
        if new_uuid:
            run(["connection", "delete", "uuid", new_uuid])
        if prev_uuid and prev_uuid != target:
            run(["-w", "30", "connection", "up", "uuid", prev_uuid, "ifname", dev], timeout=45)   # go back to where we were
        write_result("failed", ssid, scrub(friendly_error(err, ssid), password))
        return
    for p in profiles:                                  # replaced an older profile for the same network
        if new_uuid and p["ssid"] == ssid and p["uuid"] != new_uuid:
            run(["connection", "delete", "uuid", p["uuid"]])
    ip, _, _ = device_info(dev)
    write_result("ok", ssid, "Connected.", ip)


def cmd_connect():
    d = read_payload()
    if d is None:
        return emit({"ok": False, "error": "Bad request."}, 2)
    ssid, pw = d.get("ssid"), d.get("password", "")
    kind = d.get("security", "wpa-psk")
    use_saved, hidden = bool(d.get("use_saved")), bool(d.get("hidden"))
    if not NMCLI:
        return emit({"ok": False, "error": "NetworkManager (nmcli) is not installed."})
    if bad_ssid(ssid):
        return emit({"ok": False, "error": "The network name must be 1 to 32 characters."})
    if kind not in OK_SECURITY:
        return emit({"ok": False, "error": "This kind of network is not supported here (only open and WPA2/WPA3 password networks)."})
    if not use_saved:
        err = bad_password(pw, kind)
        if err:
            return emit({"ok": False, "error": err})
    dev, _ = wifi_device()
    if dev is None:
        return emit({"ok": False, "error": "No Wi-Fi adapter was found."})
    rc, out, _ = run(["radio", "wifi"])
    if rc != 0 or out.strip() != "enabled":
        return emit({"ok": False, "error": "Wi-Fi is turned off. Turn it on first."})
    write_result("working", ssid, "Connecting...")
    pid = os.fork()
    if pid:                                             # the web page gets its answer now
        return emit({"ok": True, "started": True})
    try:                                                # child: keep going on our own
        os.setsid()
        devnull = os.open(os.devnull, os.O_RDWR)
        for fd in (0, 1, 2):
            os.dup2(devnull, fd)
        finish_connect(ssid, pw if isinstance(pw, str) else "", hidden, kind, use_saved, dev["name"])
    except Exception as e:                              # never leave the page waiting on a crash
        write_result("failed", ssid, scrub("Unexpected error: %s" % e, pw if isinstance(pw, str) else ""))
    finally:
        os._exit(0)


def cmd_forget():
    d = read_payload()
    if d is None or bad_ssid(d.get("ssid")):
        return emit({"ok": False, "error": "Bad request."}, 2)
    if not NMCLI:
        return emit({"ok": False, "error": "NetworkManager (nmcli) is not installed."})
    n = 0
    for p in saved_profiles():
        if p["ssid"] == d["ssid"]:
            rc, _, _ = run(["connection", "delete", "uuid", p["uuid"]])
            n += 1 if rc == 0 else 0
    return emit({"ok": n > 0, "removed": n, "error": "" if n else "That network was not saved."})


def main(argv):
    if len(argv) < 2:
        return emit({"ok": False, "error": "usage"}, 2)
    c = argv[1]
    if c == "status" and len(argv) == 2:
        return cmd_status()
    if c == "scan" and len(argv) == 3 and argv[2] in ("yes", "no"):
        return cmd_scan(argv[2] == "yes")
    if c == "power" and len(argv) == 3:
        return cmd_power(argv[2])
    if c == "connect" and len(argv) == 2:
        return cmd_connect()
    if c == "forget" and len(argv) == 2:
        return cmd_forget()
    return emit({"ok": False, "error": "usage"}, 2)


if __name__ == "__main__":
    sys.exit(main(sys.argv))
