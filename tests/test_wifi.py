"""Wi-Fi tab: the root helper (scripts/wifi_helper.py) against a fake nmcli, and the JSON endpoints in front of it."""
import json
import os
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import unittest
import urllib.error
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
HELPER = os.path.join(ROOT, "scripts", "wifi_helper.py")
FAKE = os.path.join(ROOT, "tests", "fake_nmcli.py")

PREV = "11111111-1111-4111-8111-111111111111"


def base_state():
    return {
        "nm_running": True, "radio": True, "ip": "192.168.1.50", "active_uuid": PREV, "delay": 0,
        "devices": [{"name": "wlan0", "type": "wifi", "state": "connected", "connection": "Home"},
                    {"name": "eth0", "type": "ethernet", "state": "unavailable", "connection": ""}],
        "aps": [
            {"ssid": "Home", "signal": 80, "security": "WPA2", "chan": 6, "in_use": True},
            {"ssid": "Home", "signal": 55, "security": "WPA2", "chan": 11, "in_use": False},      # same network, weaker AP
            {"ssid": "Cafe: Free", "signal": 40, "security": "", "chan": 1, "in_use": False},     # open, with a colon in the name
            {"ssid": "Neighbour", "signal": 62, "security": "WPA2 WPA3", "chan": 36, "in_use": False},
            {"ssid": "Modern", "signal": 30, "security": "WPA3", "chan": 44, "in_use": False},
            {"ssid": "Corp", "signal": 70, "security": "WPA2 802.1X", "chan": 3, "in_use": False},
            {"ssid": "", "signal": 90, "security": "WPA2", "chan": 9, "in_use": False},           # hidden network: no name
        ],
        "profiles": [{"name": "Home", "uuid": PREV, "ssid": "Home", "psk": "homepass1", "key_mgmt": "wpa-psk", "hidden": False}],
        "passwords": {"Home": "homepass1", "Neighbour": "neighbourpw", "Modern": "modernpass", "Cafe: Free": None},
    }


class Env:
    """A temp dir holding fake nmcli state and the helper's result file."""

    def __init__(self):
        self.dir = tempfile.mkdtemp()
        self.state = os.path.join(self.dir, "state.json")
        self.result = os.path.join(self.dir, "wifi_result.json")
        self.set(base_state())
        self.env = dict(os.environ, CC_NMCLI=FAKE, CC_FAKE_STATE=self.state, CC_WIFI_RESULT=self.result)
        self.env.pop("SUDO_UID", None)

    def set(self, s):
        json.dump(s, open(self.state, "w"))

    def get(self):
        return json.load(open(self.state))

    def log(self):
        p = self.state + ".log"
        return [json.loads(l) for l in open(p)] if os.path.exists(p) else []

    def helper(self, *args, stdin=None, env=None):
        p = subprocess.run([sys.executable, HELPER] + list(args), input=stdin, stdout=subprocess.PIPE,
                           stderr=subprocess.PIPE, universal_newlines=True, env=env or self.env, timeout=60)
        return json.loads(p.stdout), p

    def wait_result(self, timeout=15):
        end = time.time() + timeout
        while time.time() < end:
            try:
                r = json.load(open(self.result))
                if r["state"] != "working":
                    return r
            except (OSError, ValueError):
                pass
            time.sleep(0.1)
        raise AssertionError("no result")

    def cleanup(self):
        shutil.rmtree(self.dir, ignore_errors=True)


class HelperTests(unittest.TestCase):
    def setUp(self):
        self.e = Env(); self.addCleanup(self.e.cleanup)

    def test_status_connected(self):
        r, _ = self.e.helper("status")
        self.assertTrue(r["available"]); self.assertTrue(r["radio_on"])
        self.assertEqual((r["ssid"], r["signal"], r["ip"], r["device"]), ("Home", 80, "192.168.1.50", "wlan0"))
        self.assertFalse(r["ethernet"])

    def test_status_ethernet_noticed(self):
        s = self.e.get(); s["devices"][1]["state"] = "connected"; self.e.set(s)
        self.assertTrue(self.e.helper("status")[0]["ethernet"])

    def test_not_available_cases(self):
        s = self.e.get(); s["nm_running"] = False; self.e.set(s)
        r, _ = self.e.helper("status")
        self.assertEqual((r["available"], r["reason"]), (False, "nm_not_running"))
        r, _ = self.e.helper("status", env=dict(self.e.env, CC_NMCLI=""))
        self.assertEqual(r["reason"], "nmcli_missing")

    def test_no_wifi_adapter(self):
        s = self.e.get(); s["devices"] = [s["devices"][1]]; self.e.set(s)
        self.assertFalse(self.e.helper("status")[0]["has_device"])
        self.assertIn("No Wi-Fi adapter", self.e.helper("scan", "no")[0]["error"])

    def test_scan_merges_sorts_and_classifies(self):
        r, _ = self.e.helper("scan", "yes")
        self.assertTrue(r["ok"])
        names = [n["ssid"] for n in r["networks"]]
        self.assertEqual(names, ["Home", "Corp", "Neighbour", "Cafe: Free", "Modern"])     # in use first, then by signal; hidden dropped
        by = {n["ssid"]: n for n in r["networks"]}
        self.assertEqual(by["Home"]["signal"], 80)                                         # strongest of the two access points
        self.assertTrue(by["Home"]["in_use"]); self.assertTrue(by["Home"]["saved"])
        self.assertEqual(by["Cafe: Free"]["security"], "open"); self.assertFalse(by["Cafe: Free"]["secure"])
        self.assertEqual(by["Neighbour"]["security"], "wpa-psk")
        self.assertEqual(by["Modern"]["security"], "sae")
        self.assertEqual(by["Corp"]["security"], "enterprise"); self.assertFalse(by["Corp"]["supported"])
        self.assertIn(["device", "wifi", "list", "ifname", "wlan0", "--rescan", "yes"], [a[-7:] for a in self.e.log() if len(a) >= 7])

    def test_scan_without_rescan_and_out_of_range(self):
        s = self.e.get()
        s["profiles"].append({"name": "Old", "uuid": "22222222-2222-4222-8222-222222222222", "ssid": "Old", "psk": "x", "key_mgmt": "wpa-psk", "hidden": False})
        self.e.set(s)
        r, _ = self.e.helper("scan", "no")
        self.assertEqual(r["saved_out_of_range"], ["Old"])
        self.assertTrue(any(a[-2:] == ["--rescan", "no"] for a in self.e.log()))

    def test_power_off_and_on(self):
        self.assertTrue(self.e.helper("power", "off")[0]["ok"])
        r, _ = self.e.helper("status")
        self.assertFalse(r["radio_on"])
        s, _ = self.e.helper("scan", "no")
        self.assertEqual(s["error"], "Wi-Fi is turned off.")
        self.assertTrue(self.e.helper("power", "on")[0]["ok"])
        self.assertTrue(self.e.helper("status")[0]["radio_on"])
        _, p = self.e.helper("power", "sideways"); self.assertEqual(p.returncode, 2)

    def connect(self, **kw):
        r, _ = self.e.helper("connect", stdin=json.dumps(kw))
        return r

    def test_connect_new_network(self):
        r = self.connect(ssid="Neighbour", password="neighbourpw", security="wpa-psk")
        self.assertTrue(r["ok"] and r["started"])
        res = self.e.wait_result()
        self.assertEqual((res["state"], res["ssid"], res["ip"]), ("ok", "Neighbour", "192.168.1.50"))
        s = self.e.get()
        self.assertEqual([p["ssid"] for p in s["profiles"]].count("Neighbour"), 1)
        self.assertEqual(s["devices"][0]["connection"], "Neighbour")

    def test_connect_replaces_older_profile_for_same_network(self):
        r = self.connect(ssid="Home", password="homepass1", security="wpa-psk")
        self.assertTrue(r["ok"]); self.assertEqual(self.e.wait_result()["state"], "ok")
        self.assertEqual([p["ssid"] for p in self.e.get()["profiles"]], ["Home"])
        self.assertNotEqual(self.e.get()["active_uuid"], PREV)

    def test_wrong_password_cleans_up_and_returns_to_old_network(self):
        r = self.connect(ssid="Neighbour", password="not-the-password", security="wpa-psk")
        self.assertTrue(r["started"])
        res = self.e.wait_result()
        self.assertEqual(res["state"], "failed")
        self.assertIn("password was not accepted", res["message"])
        self.assertNotIn("not-the-password", json.dumps(res))
        s = self.e.get()
        self.assertEqual([p["ssid"] for p in s["profiles"]], ["Home"])          # the failed profile was removed
        self.assertEqual(s["active_uuid"], PREV)                                 # and we went back to Home

    def test_out_of_range_network(self):
        s = self.e.get(); s["aps"] = [a for a in s["aps"] if a["ssid"] != "Neighbour"]; self.e.set(s)
        self.connect(ssid="Neighbour", password="neighbourpw", security="wpa-psk")
        res = self.e.wait_result()
        self.assertEqual(res["state"], "failed"); self.assertIn("not in range", res["message"])

    def test_open_and_hidden_networks(self):
        self.connect(ssid="Cafe: Free", security="open"); self.assertEqual(self.e.wait_result()["state"], "ok")
        s = self.e.get(); s["aps"].append({"ssid": "Secret", "signal": 50, "security": "WPA2", "chan": 1, "in_use": False})
        s["passwords"]["Secret"] = "secretpass"; self.e.set(s)
        self.connect(ssid="Secret", password="secretpass", security="wpa-psk", hidden=True)
        self.assertEqual(self.e.wait_result()["state"], "ok")
        self.assertTrue([p for p in self.e.get()["profiles"] if p["ssid"] == "Secret"][0]["hidden"])

    def test_use_saved_profile(self):
        s = self.e.get(); s["devices"][0].update(state="disconnected", connection=""); s["active_uuid"] = ""; self.e.set(s)
        self.assertTrue(self.connect(ssid="Home", use_saved=True, security="wpa-psk")["started"])
        self.assertEqual(self.e.wait_result()["state"], "ok")
        self.assertEqual(self.e.get()["active_uuid"], PREV)
        self.connect(ssid="Nowhere", use_saved=True, security="wpa-psk")
        self.assertIn("not saved", self.e.wait_result()["message"])

    def test_bad_requests_are_refused_before_anything_changes(self):
        for kw, text in [(dict(ssid="X", password="short", security="wpa-psk"), "8 to 63"),
                         (dict(ssid="", password="longenough1", security="wpa-psk"), "1 to 32"),
                         (dict(ssid="x" * 33, password="longenough1", security="wpa-psk"), "1 to 32"),
                         (dict(ssid="A\nB", password="longenough1", security="wpa-psk"), "1 to 32"),
                         (dict(ssid="Corp", password="longenough1", security="enterprise"), "not supported"),
                         (dict(ssid="X", password="line\nbreak1", security="wpa-psk"), "not allowed")]:
            r = self.connect(**kw)
            self.assertFalse(r["ok"], kw); self.assertIn(text, r["error"])
        self.assertFalse(any(a[:2] == ["connection", "add"] for a in self.e.log()))
        self.assertFalse(os.path.exists(self.e.result))

    def test_connect_refused_while_wifi_is_off(self):
        self.e.helper("power", "off")
        self.assertIn("turned off", self.connect(ssid="Neighbour", password="neighbourpw", security="wpa-psk")["error"])

    def test_forget(self):
        r, _ = self.e.helper("forget", stdin=json.dumps({"ssid": "Home"}))
        self.assertEqual((r["ok"], r["removed"]), (True, 1))
        self.assertEqual(self.e.get()["profiles"], [])
        r, _ = self.e.helper("forget", stdin=json.dumps({"ssid": "Home"}))
        self.assertFalse(r["ok"])

    def test_stale_working_result_is_not_left_hanging(self):
        json.dump({"state": "working", "ssid": "X", "message": "", "ip": "", "ts": time.time() - 1000}, open(self.e.result, "w"))
        self.assertEqual(self.e.helper("status")[0]["result"]["state"], "failed")

    def test_usage_errors(self):
        for args in ([], ["frobnicate"], ["scan"], ["scan", "maybe"], ["status", "extra"]):
            _, p = self.e.helper(*args); self.assertEqual(p.returncode, 2, args)

    def test_nothing_is_run_through_a_shell(self):
        self.connect(ssid="a; touch /tmp/pwned_by_wifi", password="pa$$word; echo hi", security="wpa-psk")
        self.e.wait_result()
        self.assertFalse(os.path.exists("/tmp/pwned_by_wifi"))
        add = [a for a in self.e.log() if a[:2] == ["connection", "add"]][0]
        self.assertIn("a; touch /tmp/pwned_by_wifi", add); self.assertIn("pa$$word; echo hi", add)    # passed through as plain arguments


def free_port():
    s = socket.socket(); s.bind(("127.0.0.1", 0)); p = s.getsockname()[1]; s.close(); return p


class WifiWebTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.e = Env()
        cls.rundir = tempfile.mkdtemp(); os.makedirs(os.path.join(cls.rundir, "cmd"))
        cls.port = free_port()
        env = dict(cls.e.env, CC_WEB_NOAUTH="1", CC_RUN_DIR=cls.rundir, CC_DATA_DIR=cls.rundir, CC_WIFI_NOSUDO="1", CC_WIFI_HELPER=HELPER)
        web = os.path.join(ROOT, "web")
        cls.php = subprocess.Popen(["php", "-S", "127.0.0.1:%d" % cls.port, "-t", web, os.path.join(web, "router.php")],
                                   env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(50):
            try:
                socket.create_connection(("127.0.0.1", cls.port), 0.2).close(); break
            except OSError:
                time.sleep(0.1)

    @classmethod
    def tearDownClass(cls):
        cls.php.terminate(); cls.php.wait(); cls.e.cleanup(); shutil.rmtree(cls.rundir, ignore_errors=True)

    def setUp(self):
        self.e.set(base_state())
        try:
            os.remove(self.e.result)
        except OSError:
            pass

    def call(self, action, body=None, header=True, extra=""):
        url = "http://127.0.0.1:%d/plugin.php?page=ajax.php&nopage=1&action=%s%s" % (self.port, action, extra)
        req = urllib.request.Request(url, data=None if body is None else json.dumps(body).encode(), method="GET" if body is None else "POST")
        if body is not None:
            req.add_header("Content-Type", "application/json")
        if header:
            req.add_header("X-Requested-With", "XMLHttpRequest")
        try:
            with urllib.request.urlopen(req, timeout=30) as r:
                return r.status, json.loads(r.read().decode())
        except urllib.error.HTTPError as e:
            return e.code, json.loads(e.read().decode())

    def test_status_and_via_wifi(self):
        code, r = self.call("wifi_status")
        self.assertEqual(code, 200); self.assertEqual(r["ssid"], "Home"); self.assertFalse(r["via_wifi"])
        s = self.e.get(); s["ip"] = "127.0.0.1"; self.e.set(s)                # the address this page was reached on
        self.assertTrue(self.call("wifi_status")[1]["via_wifi"])

    def test_scan(self):
        code, r = self.call("wifi_scan", extra="&rescan=1")
        self.assertTrue(r["ok"]); self.assertEqual(r["networks"][0]["ssid"], "Home")
        self.assertTrue(any(a[-2:] == ["--rescan", "yes"] for a in self.e.log()))

    def test_changes_need_the_page_header_and_post(self):
        for action, body in (("wifi_power", {"on": False}), ("wifi_connect", {"ssid": "x"}), ("wifi_forget", {"ssid": "Home"})):
            self.assertEqual(self.call(action, body, header=False)[0], 403, action)
            self.assertEqual(self.call(action)[0], 403, action)               # GET refused too
        self.assertEqual(self.e.get()["radio"], True)

    def test_power_connect_forget_through_the_endpoint(self):
        self.assertTrue(self.call("wifi_power", {"on": False})[1]["ok"]); self.assertFalse(self.e.get()["radio"])
        self.assertTrue(self.call("wifi_power", {"on": True})[1]["ok"]); self.assertTrue(self.e.get()["radio"])
        code, r = self.call("wifi_connect", {"ssid": "Neighbour", "password": "neighbourpw", "security": "wpa-psk"})
        self.assertTrue(r["started"])
        self.assertEqual(self.e.wait_result()["state"], "ok")
        self.assertEqual(self.call("wifi_status")[1]["result"]["ssid"], "Neighbour")
        self.assertTrue(self.call("wifi_forget", {"ssid": "Neighbour"})[1]["ok"])
        self.assertEqual(self.call("wifi_connect", {"ssid": ""})[0], 400)

    def test_helper_missing_gives_a_readable_error(self):
        s = os.path.join(self.e.dir, "missing"); 
        out = subprocess.run(["php", "-r", "putenv('CC_WIFI_NOSUDO=1'); putenv('CC_WIFI_HELPER=/nonexistent/helper'); require '%s/web/lib.php'; echo json_encode(cc_wifi_call(array('status')));" % ROOT],
                             stdout=subprocess.PIPE, universal_newlines=True, env=dict(os.environ, CC_RUN_DIR=self.rundir, CC_DATA_DIR=self.rundir)).stdout
        r = json.loads(out); self.assertFalse(r["ok"]); self.assertTrue(r["helper_missing"])


if __name__ == "__main__":
    unittest.main()


class SettingsParityTests(unittest.TestCase):
    """The settings list exists twice (Python daemon and PHP page); a key missing from either is silently dropped on save."""

    def test_php_and_daemon_know_the_same_settings(self):
        sys.path.insert(0, os.path.join(ROOT, "counter"))
        import car_counter as cc
        out = subprocess.check_output(["php", "-r", "require '%s/web/lib.php'; echo json_encode(array_keys(cc_schema()));" % ROOT],
                                      env=dict(os.environ, CC_DATA_DIR="/tmp", CC_RUN_DIR="/tmp")).decode()
        php_keys = set(json.loads(out))
        py_keys = set(cc.SCHEMA)
        self.assertEqual(sorted(php_keys - py_keys), [], "in the PHP page but not the daemon")
        self.assertEqual(sorted(py_keys - php_keys - {"schedules"}), [], "in the daemon but not the PHP page")


class NetworkChoiceTests(unittest.TestCase):
    """Reaching the BaldrickSignals board / Falcon Player through a chosen network connection (Ethernet or Wi-Fi)."""

    @classmethod
    def setUpClass(cls):
        sys.path.insert(0, os.path.join(ROOT, "counter"))
        import car_counter as cc
        cls.cc = cc
        import http.server, threading

        class H(http.server.BaseHTTPRequestHandler):
            def do_GET(self):
                body = b"1700000000, 7\n"
                self.send_response(200); self.send_header("Content-Type", "text/csv"); self.end_headers(); self.wfile.write(body)

            def log_message(self, *a):
                pass
        cls.srv = http.server.HTTPServer(("127.0.0.1", 0), H)
        cls.port = cls.srv.server_address[1]
        threading.Thread(target=cls.srv.serve_forever, daemon=True).start()

    @classmethod
    def tearDownClass(cls):
        cls.srv.shutdown()

    def test_interface_names_are_checked(self):
        n = self.cc.normalize_iface
        self.assertEqual((n("eth0"), n(" wlan0 "), n("end0"), n("enp0s3")), ("eth0", "wlan0", "end0", "enp0s3"))
        for bad in ("", None, "eth0; rm -rf /", "../etc", "a b", "x" * 16, "-eth0"):
            self.assertEqual(n(bad), "", repr(bad))

    def test_config_keeps_only_real_interface_names(self):
        c = self.cc.normalize_config({"people_interface": "eth0", "fpp_interface": "wlan0"})
        self.assertEqual((c["people_interface"], c["fpp_interface"]), ("eth0", "wlan0"))
        c = self.cc.normalize_config({"people_interface": "eth0 && reboot"})
        self.assertEqual(c["people_interface"], "")

    def test_loopback_connection_reaches_the_board(self):
        host = "127.0.0.1:%d" % self.port
        ok, text, _ = self.cc.fetch_board_csv(host, iface="")
        self.assertTrue(ok)
        ok, text, _ = self.cc.fetch_board_csv(host, iface="lo")                # pinned to the loopback connection
        self.assertTrue(ok, text); self.assertIn("1700000000", text)

    def test_missing_connection_gives_a_clear_message(self):
        ok, text, _ = self.cc.fetch_board_csv("127.0.0.1:%d" % self.port, iface="nope0")
        self.assertFalse(ok)
        self.assertIn("using the nope0 connection", text)
        self.assertIn("no address", text)

    def test_ifaces_endpoint_lists_connections(self):
        out = subprocess.check_output(["php", "-r", "require '%s/web/lib.php'; echo json_encode(cc_ifaces());" % ROOT],
                                      env=dict(os.environ, CC_DATA_DIR="/tmp", CC_RUN_DIR="/tmp")).decode()
        for i in json.loads(out):
            self.assertIn(i["kind"], ("ethernet", "wifi")); self.assertNotEqual(i["name"], "lo")
