#!/usr/bin/python3
"""A tiny stand-in for NetworkManager's nmcli, just enough for tests of scripts/wifi_helper.py.

State lives in the JSON file named by CC_FAKE_STATE; every call is appended to CC_FAKE_STATE + ".log".
"""
import json
import os
import sys
import time
import uuid as uuidlib

STATE = os.environ["CC_FAKE_STATE"]


def load():
    with open(STATE) as fh:
        return json.load(fh)


def save(s):
    with open(STATE, "w") as fh:
        json.dump(s, fh)


def esc(v):
    return str(v).replace("\\", "\\\\").replace(":", "\\:")


def die(msg, code=4):
    sys.stderr.write(msg + "\n")
    sys.exit(code)


def wlan(s):
    return [d for d in s["devices"] if d["type"] == "wifi"][0]


def activate(s, prof):
    d = wlan(s)
    s["active_uuid"] = prof["uuid"]
    d["state"], d["connection"] = "connected", prof["name"]
    for ap in s["aps"]:
        ap["in_use"] = ap["ssid"] == prof["ssid"]


def main():
    argv = sys.argv[1:]
    with open(STATE + ".log", "a") as fh:
        fh.write(json.dumps(argv) + "\n")
    s = load()
    a, fields = [], ""
    i = 0
    while i < len(argv):
        if argv[i] in ("-t", "-g"):
            i += 1
        elif argv[i] in ("-w", "-f"):
            if argv[i] == "-f":
                fields = argv[i + 1]
            i += 2
        else:
            a.append(argv[i]); i += 1
    if s.get("delay"):
        time.sleep(s["delay"])

    if a[:1] == ["general"]:
        if not s["nm_running"]:
            die("Error: NetworkManager is not running.", 8)
        print("running"); return

    if a[:2] == ["radio", "wifi"]:
        if len(a) == 2:
            print("enabled" if s["radio"] else "disabled"); return
        s["radio"] = (a[2] == "on")
        d = wlan(s)
        if not s["radio"]:
            d["state"], d["connection"], s["active_uuid"] = "unavailable", "", ""
            for ap in s["aps"]:
                ap["in_use"] = False
        else:
            d["state"] = "disconnected"
        save(s); return

    if a[:2] == ["device", "status"]:
        for d in s["devices"]:
            print(":".join(esc(d.get(k, "")) for k in ("name", "type", "state", "connection")))
        return

    if a[:2] == ["device", "show"]:
        d = wlan(s)
        if d["state"] == "connected":
            print("GENERAL.CONNECTION:" + esc(d["connection"]))
            print("GENERAL.CON-UUID:" + s["active_uuid"])
            print("IP4.ADDRESS[1]:" + s["ip"] + "/24")
        else:
            print("GENERAL.CONNECTION:--"); print("GENERAL.CON-UUID:")
        return

    if a[:3] == ["device", "wifi", "list"]:
        if not s["radio"]:
            die("Error: Wi-Fi is disabled.", 1)
        for ap in s["aps"]:
            print(":".join([("*" if ap.get("in_use") else " "), esc(ap["ssid"]), str(ap["signal"]),
                            esc(ap["security"]), str(ap.get("chan", 6))]))
        return

    if a[:2] == ["connection", "show"]:
        if len(a) == 2:
            for p in s["profiles"]:
                print(":".join([esc(p["name"]), p["uuid"], "802-11-wireless"]))
            return
        u = a[3]
        for p in s["profiles"]:
            if p["uuid"] == u:
                print("802-11-wireless.ssid:" + esc(p["ssid"])); return
        die("Error: not found", 10)

    if a[:2] == ["connection", "add"]:
        kv = dict(zip(a[2::2], a[3::2]))
        p = {"name": kv["con-name"], "ssid": kv["ssid"], "uuid": str(uuidlib.uuid4()),
             "psk": kv.get("wifi-sec.psk"), "key_mgmt": kv.get("wifi-sec.key-mgmt"), "hidden": kv.get("wifi.hidden") == "yes"}
        s["profiles"].append(p); save(s)
        print("Connection '%s' (%s) successfully added." % (p["name"], p["uuid"])); return

    if a[:2] == ["connection", "up"]:
        u = a[3]
        prof = next((p for p in s["profiles"] if p["uuid"] == u), None)
        if prof is None:
            die("Error: unknown connection.", 10)
        ap = next((x for x in s["aps"] if x["ssid"] == prof["ssid"]), None)
        if ap is None and not prof["hidden"]:
            die("Error: Connection activation failed: No network with SSID '%s' found." % prof["ssid"])
        want = s["passwords"].get(prof["ssid"])
        if want is not None and prof["psk"] != want:
            die("Error: Connection activation failed: (7) Secrets were required, but not provided.")
        activate(s, prof); save(s)
        print("Connection successfully activated."); return

    if a[:2] == ["connection", "delete"]:
        u = a[3]
        s["profiles"] = [p for p in s["profiles"] if p["uuid"] != u]
        if s["active_uuid"] == u:
            d = wlan(s); d["state"], d["connection"], s["active_uuid"] = "disconnected", "", ""
            for ap in s["aps"]:
                ap["in_use"] = False
        save(s); print("Connection deleted."); return

    die("fake nmcli: unsupported " + " ".join(argv), 2)


if __name__ == "__main__":
    main()
