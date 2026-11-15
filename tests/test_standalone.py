"""Tests: following a remote Falcon Player, and the password-protected web page."""
import base64
import datetime
import http.server
import json
import os
import shutil
import socket
import subprocess
import sys
import tempfile
import threading
import time
import unittest
import urllib.error
import urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
sys.path.insert(0, os.path.join(ROOT, "counter"))
import car_counter as cc  # noqa: E402


class FakeFpp:
    def __init__(self):
        self.status = "playing"
        outer = self

        class H(http.server.BaseHTTPRequestHandler):
            def do_GET(self):
                if self.path != "/api/fppd/status":
                    self.send_response(404); self.end_headers(); return
                body = json.dumps({"status_name": outer.status}).encode()
                self.send_response(200); self.send_header("Content-Type", "application/json"); self.end_headers()
                self.wfile.write(body)

            def log_message(self, *a):
                pass
        self.srv = http.server.HTTPServer(("127.0.0.1", 0), H)
        self.host = "127.0.0.1:%d" % self.srv.server_address[1]
        threading.Thread(target=self.srv.serve_forever, daemon=True).start()

    def close(self):
        self.srv.shutdown()


CFG = {"schedule_mode": "playing", "fpp_host": "192.168.1.50", "grace_min": 10,
       "schedules": [{"days": list(range(7)), "start": "17:00", "end": "22:00"}]}


def at(h, m=0):
    return datetime.datetime(2026, 11, 16, h, m)


class CountingStateTests(unittest.TestCase):
    def test_playing_follows_fpp(self):
        self.assertEqual(cc.counting_state(CFG, at(12), True, 0, 0, True), (True, "FPP is playing"))
        self.assertEqual(cc.counting_state(CFG, at(19), False, 0, 0, True), (False, "FPP is idle"))

    def test_unreachable_remote_fpp_falls_back_to_schedule(self):
        ok, why = cc.counting_state(CFG, at(19), False, 0, 0, False)
        self.assertTrue(ok)
        self.assertIn("unreachable", why)
        ok, why = cc.counting_state(CFG, at(12), False, 0, 0, False)
        self.assertFalse(ok)

    def test_no_address_means_no_fallback(self):
        local = dict(CFG, fpp_host="")
        self.assertEqual(cc.counting_state(local, at(19), False, 0, 0, False), (False, "FPP is idle"))


class ConfigTests(unittest.TestCase):
    def test_fpp_host_is_cleaned(self):
        self.assertEqual(cc.normalize_config({"fpp_host": "http://192.168.1.50/index.php"})["fpp_host"], "192.168.1.50")
        self.assertEqual(cc.normalize_config({"fpp_host": "not a host!"})["fpp_host"], "")

    def test_playing_mode_needs_a_falcon_player_address(self):
        self.assertEqual(cc.normalize_config({"schedule_mode": "playing"})["schedule_mode"], "window")
        self.assertEqual(cc.normalize_config({"schedule_mode": "playing", "fpp_host": "10.0.0.2"})["schedule_mode"], "playing")


class PollFppTests(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.mkdtemp()
        os.environ["CC_DATA_DIR"] = os.path.join(self.dir, "data")
        os.environ["CC_RUN_DIR"] = os.path.join(self.dir, "run")
        os.environ["CC_LOG_FILE"] = os.path.join(self.dir, "log", "c.log")
        self.paths = cc.get_paths()
        for p in (self.paths["data"], self.paths["run"], self.paths["cmd"]):
            os.makedirs(p, exist_ok=True)
        self.fpp = FakeFpp()
        cc.atomic_write_json(self.paths["config"], {"schedule_mode": "playing", "fpp_host": self.fpp.host}, 0o600)
        self.d = cc.Daemon(self.paths)

    def tearDown(self):
        self.fpp.close()
        shutil.rmtree(self.dir)
        for k in ("CC_DATA_DIR", "CC_RUN_DIR", "CC_LOG_FILE"):
            os.environ.pop(k, None)

    def test_reads_remote_fpp_status(self):
        self.d.poll_fpp()
        self.assertTrue(self.d.shared["fpp_known"])
        self.assertTrue(self.d.shared["fpp_playing"])
        self.assertTrue(self.d.counting_now(at(12))[0])
        self.fpp.status = "idle"
        self.d.poll_fpp()
        self.assertFalse(self.d.shared["fpp_playing"])

    def test_unreachable_fpp_marks_unknown(self):
        self.fpp.close()
        self.d.poll_fpp()
        self.assertFalse(self.d.shared["fpp_known"])


class UartTests(unittest.TestCase):
    """The GPIO serial port: set up automatically, safe to repeat, and the page can tell whether a restart is still needed."""

    def setUp(self):
        self.dir = tempfile.mkdtemp()
        self.boot = os.path.join(self.dir, "boot"); os.makedirs(self.boot)
        self.proc = os.path.join(self.dir, "proc_cmdline")
        self.script = os.path.join(ROOT, "scripts", "enable_uart.sh")
        self.php = os.path.join(ROOT, "web", "lib.php")

    def tearDown(self):
        shutil.rmtree(self.dir)

    def write(self, config="dtparam=audio=on\n", cmdline="console=serial0,115200 console=tty1 root=/dev/mmcblk0p2 rootwait", running=None):
        open(os.path.join(self.boot, "config.txt"), "w").write(config)
        open(os.path.join(self.boot, "cmdline.txt"), "w").write(cmdline)
        open(self.proc, "w").write(cmdline if running is None else running)

    def run_script(self):
        return subprocess.run(["bash", self.script], env=dict(os.environ, CC_BOOT_DIR=self.boot), capture_output=True, text=True)

    def state(self):
        out = subprocess.check_output(["php", "-r", "require '%s'; echo cc_uart_state();" % self.php],
                                      env=dict(os.environ, CC_BOOT_DIR=self.boot, CC_PROC_CMDLINE=self.proc)).decode()
        return out.strip()

    def test_script_switches_the_port_on_once_then_leaves_it_alone(self):
        self.write()
        self.assertEqual(self.state(), "not_enabled")
        r = self.run_script()
        self.assertEqual(r.returncode, 0, r.stderr)
        self.assertIn("Restart the Pi", r.stdout)
        cfg = open(os.path.join(self.boot, "config.txt")).read()
        cmd = open(os.path.join(self.boot, "cmdline.txt")).read()
        self.assertIn("enable_uart=1", cfg)
        self.assertNotIn("console=serial0", cmd)
        self.assertIn("console=tty1", cmd)                       # the screen console is untouched
        self.assertFalse(cmd.startswith(" "))
        self.assertIn("root=/dev/mmcblk0p2", cmd)
        files = sorted(os.listdir(self.boot))
        r2 = self.run_script()                                   # second run: nothing to do, no new backups
        self.assertEqual(r2.returncode, 0)
        self.assertIn("already", r2.stdout)
        self.assertEqual(sorted(os.listdir(self.boot)), files)
        self.assertEqual(open(os.path.join(self.boot, "config.txt")).read().count("enable_uart"), 1)

    def test_script_fixes_enable_uart_zero(self):
        self.write(config="enable_uart=0\n", cmdline="root=/dev/mmcblk0p2")
        self.assertEqual(self.run_script().returncode, 0)
        self.assertEqual(open(os.path.join(self.boot, "config.txt")).read().strip(), "enable_uart=1")

    def test_state_needs_restart_until_the_pi_has_rebooted(self):
        self.write()
        self.run_script()                                        # boot files fixed; the running kernel still has the console
        self.assertEqual(self.state(), "needs_restart")
        open(self.proc, "w").write("console=tty1 root=/dev/mmcblk0p2 rootwait")   # after the restart
        self.assertEqual(self.state(), "ready")

    def test_not_a_pi(self):
        empty = os.path.join(self.dir, "none"); os.makedirs(empty)
        out = subprocess.check_output(["php", "-r", "require '%s'; echo cc_uart_state();" % self.php],
                                      env=dict(os.environ, CC_BOOT_DIR=empty)).decode()
        self.assertEqual(out.strip(), "unknown")
        r = subprocess.run(["bash", self.script], env=dict(os.environ, CC_BOOT_DIR=empty), capture_output=True, text=True)
        self.assertEqual(r.returncode, 0)


def free_port():
    s = socket.socket(); s.bind(("127.0.0.1", 0)); p = s.getsockname()[1]; s.close(); return p


class WebTests(unittest.TestCase):
    """The standalone page: login, routing, and the JSON endpoint."""

    @classmethod
    def setUpClass(cls):
        cls.dir = tempfile.mkdtemp()
        cls.data = os.path.join(cls.dir, "data"); cls.runp = os.path.join(cls.dir, "run")
        os.makedirs(cls.data); os.makedirs(os.path.join(cls.runp, "cmd"))
        cls.pw = os.path.join(cls.dir, "web.pass")
        h = subprocess.check_output(["php", "-r", "echo password_hash('s3cret-pw', PASSWORD_DEFAULT);"]).decode()
        open(cls.pw, "w").write(h)
        cls.port = free_port()
        env = dict(os.environ, CC_DATA_DIR=cls.data, CC_RUN_DIR=cls.runp,
                   CC_LOG_FILE=os.path.join(cls.dir, "l.log"), CC_ERR_FILE=os.path.join(cls.dir, "l.err"),
                   CC_WEB_PASSWORD_FILE=cls.pw)
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
        cls.php.terminate(); cls.php.wait()
        shutil.rmtree(cls.dir)

    def get(self, path, auth=None, method="GET", body=None, headers=None):
        req = urllib.request.Request("http://127.0.0.1:%d%s" % (self.port, path), data=body, method=method)
        if auth:
            req.add_header("Authorization", "Basic " + base64.b64encode(auth.encode()).decode())
        for k, v in (headers or {}).items():
            req.add_header(k, v)
        try:
            with urllib.request.urlopen(req, timeout=10) as r:
                return r.status, r.read().decode()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode()

    def test_login_required(self):
        self.assertEqual(self.get("/")[0], 401)
        self.assertEqual(self.get("/", "admin:wrong")[0], 401)
        self.assertEqual(self.get("/", "root:s3cret-pw")[0], 401)
        code, html = self.get("/", "admin:s3cret-pw")
        self.assertEqual(code, 200)
        self.assertIn('id="cc"', html)
        self.assertNotIn("Only while FPP is playing'], <?php", html)

    def test_only_two_pages_are_served(self):
        for path in ("/lib.php", "/ui.php", "/ajax.php", "/theme.css", "/../counter/car_counter.py", "/router.php"):
            self.assertEqual(self.get(path, "admin:s3cret-pw")[0], 404, path)

    def test_ajax_endpoint_and_playing_mode_offered(self):
        code, body = self.get("/plugin.php?plugin=x&page=ajax.php&nopage=1&action=config", "admin:s3cret-pw")
        self.assertEqual(code, 200)
        self.assertIn("fpp_host", json.loads(body))
        self.assertEqual(self.get("/plugin.php?page=other.php", "admin:s3cret-pw")[0], 404)
        code, html = self.get("/", "admin:s3cret-pw")
        self.assertIn("Only while FPP is playing", html)

    def test_ports_reports_serial_port_state(self):
        code, body = self.get("/plugin.php?page=ajax.php&nopage=1&action=ports", "admin:s3cret-pw")
        self.assertEqual(code, 200)
        self.assertIn(json.loads(body)["uart"], ("ready", "needs_restart", "not_enabled", "unknown"))

    def test_only_allowed_service_actions(self):
        url = "/plugin.php?page=ajax.php&nopage=1&action=service"
        hdr = {"X-Requested-With": "XMLHttpRequest", "Content-Type": "application/json"}
        code, body = self.get(url, "admin:s3cret-pw", "POST", b'{"op":"rm -rf /"}', hdr)
        self.assertEqual(json.loads(body)["output"], "bad op")

    def test_changes_need_the_page_header(self):
        url = "/plugin.php?plugin=x&page=ajax.php&nopage=1&action=save_config"
        code, _ = self.get(url, "admin:s3cret-pw", "POST", b'{"show_name":"X"}')
        self.assertEqual(code, 403)
        code, body = self.get(url, "admin:s3cret-pw", "POST", b'{"show_name":"Roadside","fpp_host":"http://192.168.1.50/x","schedule_mode":"playing"}',
                              {"X-Requested-With": "XMLHttpRequest", "Content-Type": "application/json"})
        self.assertEqual(code, 200, body)
        code, body = self.get("/plugin.php?plugin=x&page=ajax.php&nopage=1&action=config", "admin:s3cret-pw")
        cfg = json.loads(body)
        self.assertEqual((cfg["show_name"], cfg["fpp_host"], cfg["schedule_mode"]), ("Roadside", "192.168.1.50", "playing"))
        self.assertNotIn("secret", cfg)

    def test_no_password_file_refuses(self):
        port = free_port()
        env = dict(os.environ, CC_DATA_DIR=self.data, CC_RUN_DIR=self.runp,
                   CC_WEB_PASSWORD_FILE=os.path.join(self.dir, "missing"))
        web = os.path.join(ROOT, "web")
        p = subprocess.Popen(["php", "-S", "127.0.0.1:%d" % port, "-t", web, os.path.join(web, "router.php")],
                             env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        try:
            for _ in range(50):
                try:
                    socket.create_connection(("127.0.0.1", port), 0.2).close(); break
                except OSError:
                    time.sleep(0.1)
            try:
                urllib.request.urlopen("http://127.0.0.1:%d/" % port, timeout=5)
                code = 200
            except urllib.error.HTTPError as e:
                code = e.code
            self.assertEqual(code, 503)
        finally:
            p.terminate(); p.wait()


if __name__ == "__main__":
    unittest.main()
