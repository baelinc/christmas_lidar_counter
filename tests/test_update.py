"""Update checker (daemon side) and the installer helper (scripts/updater.py) against a local fake GitHub."""
import http.server
import importlib.util
import io
import os
import shutil
import sys
import tarfile
import tempfile
import threading
import unittest

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "counter"))
import car_counter as cc  # noqa: E402

spec = importlib.util.spec_from_file_location("updater", os.path.join(ROOT, "scripts", "updater.py"))
upd = importlib.util.module_from_spec(spec)
spec.loader.exec_module(upd)


def make_tar(path, files, top="christmas_lidar_counter-main", extra=None):
    with tarfile.open(path, "w:gz") as tf:
        for name, data in files.items():
            ti = tarfile.TarInfo("%s/%s" % (top, name))
            ti.size = len(data)
            ti.mode = 0o755 if name.endswith((".sh", ".py")) else 0o644
            tf.addfile(ti, io.BytesIO(data))
        for ti in (extra or []):
            tf.addfile(ti)


class VersionTests(unittest.TestCase):
    def test_parse_and_compare(self):
        self.assertEqual(cc.parse_version("1.2.0\n"), (1, 2, 0))
        self.assertIsNone(cc.parse_version("<html>"))
        self.assertIsNone(cc.parse_version(""))
        self.assertTrue(cc.version_newer("1.10.0", "1.9.9"))
        self.assertFalse(cc.version_newer("1.1", "1.1.0"))
        self.assertFalse(cc.version_newer("1.0.0", "1.1.0"))
        self.assertFalse(cc.version_newer("junk", "1.0.0"))

    def test_changelog_since(self):
        text = "# What's new\n\n## 1.2.0\n- b\n\n## 1.1.0\n- a\n\n## 1.0.0\n- old\n"
        out = cc.changelog_since(text, "1.1.0")
        self.assertIn("1.2.0", out)
        self.assertNotIn("1.1.0", out)
        self.assertNotIn("old", out)


class Handler(http.server.BaseHTTPRequestHandler):
    files = {}

    def do_GET(self):
        body = self.files.get(self.path.lstrip("/"))
        if body is None:
            self.send_response(404); self.end_headers(); return
        self.send_response(200); self.end_headers(); self.wfile.write(body)

    def log_message(self, *a):
        pass


class CheckTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.srv = http.server.HTTPServer(("127.0.0.1", 0), Handler)
        threading.Thread(target=cls.srv.serve_forever, daemon=True).start()
        cls.old = cc.UPDATE_BASE
        cc.UPDATE_BASE = "http://127.0.0.1:%d/" % cls.srv.server_port

    @classmethod
    def tearDownClass(cls):
        cc.UPDATE_BASE = cls.old
        cls.srv.shutdown()

    def test_newer_available(self):
        Handler.files = {"VERSION": b"9.0.0\n", "CHANGELOG.md": b"## 9.0.0\n- big\n"}
        i = cc.check_for_update("1.1.0")
        self.assertTrue(i["ok"] and i["available"])
        self.assertIn("big", i["notes"])

    def test_same_version(self):
        Handler.files = {"VERSION": b"1.1.0\n"}
        i = cc.check_for_update("1.1.0")
        self.assertTrue(i["ok"])
        self.assertFalse(i["available"])

    def test_garbage_and_missing(self):
        Handler.files = {"VERSION": b"<html>oops</html>"}
        i = cc.check_for_update("1.1.0")
        self.assertFalse(i["ok"])
        self.assertTrue(i["error"])
        Handler.files = {}
        self.assertFalse(cc.check_for_update("1.1.0")["ok"])


class ExtractTests(unittest.TestCase):
    def setUp(self):
        self.d = tempfile.mkdtemp()

    def tearDown(self):
        shutil.rmtree(self.d, ignore_errors=True)

    def test_good(self):
        t = os.path.join(self.d, "a.tgz")
        make_tar(t, {"VERSION": b"2.0.0\n", "sub/x.txt": b"hi"})
        src = upd.safe_extract(t, os.path.join(self.d, "o"))
        self.assertTrue(os.path.isfile(os.path.join(src, "sub", "x.txt")))

    def test_path_escape(self):
        t = os.path.join(self.d, "a.tgz")
        with tarfile.open(t, "w:gz") as tf:
            ti = tarfile.TarInfo("top/../../evil"); ti.size = 1
            tf.addfile(ti, io.BytesIO(b"x"))
        with self.assertRaises(ValueError):
            upd.safe_extract(t, os.path.join(self.d, "o"))
        self.assertFalse(os.path.exists(os.path.join(self.d, "evil")))

    def test_symlink_refused(self):
        t = os.path.join(self.d, "a.tgz")
        ln = tarfile.TarInfo("top/link"); ln.type = tarfile.SYMTYPE; ln.linkname = "/etc/passwd"
        make_tar(t, {"VERSION": b"2.0.0\n"}, top="top", extra=[ln])
        with self.assertRaises(ValueError):
            upd.safe_extract(t, os.path.join(self.d, "o"))


def good_files(version):
    f = {n: b"#!/bin/bash\n# --update\n" if n == "install.sh" else b"x=1\n" for n in upd.REQUIRED}
    f["VERSION"] = (version + "\n").encode()
    f["counter/car_counter.py"] = b"VERSION = '%s'\n" % version.encode()
    f["web/index.php"] = f["web/router.php"] = f["web/ajax.php"] = b"<?php echo 1;\n"
    return f


class VerifyAndRunTests(unittest.TestCase):
    def setUp(self):
        self.d = tempfile.mkdtemp()
        self.saved = {k: getattr(upd, k) for k in ("APP", "RUN_DIR", "WORK", "ENV_FILE", "TARBALL_URL", "VERSION_URL", "HEALTH_WAIT")}
        upd.APP = os.path.join(self.d, "app"); upd.RUN_DIR = os.path.join(self.d, "run"); upd.WORK = os.path.join(self.d, "work")
        upd.ENV_FILE = os.path.join(self.d, "none.env"); upd.HEALTH_WAIT = 1
        os.makedirs(upd.APP); os.makedirs(upd.RUN_DIR)
        with open(os.path.join(upd.APP, "VERSION"), "w") as fh:
            fh.write("1.0.0\n")
        with open(os.path.join(upd.APP, "marker"), "w") as fh:
            fh.write("old")
        self.calls = []
        self.saved_fn = (upd.http_get, upd.run_installer, upd.wait_healthy, upd.systemctl)
        upd.systemctl = lambda *a, **k: self.calls.append(a)

    def tearDown(self):
        upd.http_get, upd.run_installer, upd.wait_healthy, upd.systemctl = self.saved_fn
        for k, v in self.saved.items():
            setattr(upd, k, v)
        shutil.rmtree(self.d, ignore_errors=True)

    def serve(self, version, files=None):
        t = os.path.join(self.d, "remote.tgz")
        make_tar(t, files or good_files(version))
        blob = open(t, "rb").read()
        upd.http_get = lambda url, limit: version.encode() if url == upd.VERSION_URL else blob

    def test_verify_rejects_missing_and_old(self):
        t = os.path.join(self.d, "a.tgz")
        f = good_files("2.0.0"); del f["web/ajax.php"]
        make_tar(t, f)
        os.makedirs(upd.WORK)
        src = upd.safe_extract(t, os.path.join(self.d, "o"))
        with self.assertRaises(ValueError):
            upd.verify(src, "1.0.0")
        make_tar(t, good_files("1.0.0"))
        src = upd.safe_extract(t, os.path.join(self.d, "o2"))
        with self.assertRaises(ValueError):
            upd.verify(src, "1.0.0")

    def test_success(self):
        self.serve("2.0.0")
        upd.run_installer = lambda src: (0, "/dev/null")
        upd.wait_healthy = lambda: True
        self.assertEqual(upd.run(), 0)
        self.assertEqual(upd.read_result()["state"], "ok")
        self.assertTrue(os.path.isdir(upd.APP + ".prev"))

    def test_already_latest(self):
        self.serve("1.0.0")
        self.assertEqual(upd.run(), 0)
        self.assertEqual(upd.read_result()["state"], "uptodate")

    def test_installer_failure_rolls_back(self):
        self.serve("2.0.0")
        def bad(src):
            shutil.rmtree(upd.APP); os.makedirs(upd.APP)            # the installer wrecked the app folder
            return 1, "/dev/null"
        upd.run_installer = bad
        self.assertEqual(upd.run(), 1)
        r = upd.read_result()
        self.assertEqual(r["state"], "failed")
        self.assertTrue(r["rolled_back"])
        self.assertEqual(open(os.path.join(upd.APP, "marker")).read(), "old")

    def test_unhealthy_rolls_back(self):
        self.serve("2.0.0")
        upd.run_installer = lambda src: (0, "/dev/null")
        upd.wait_healthy = lambda: False
        self.assertEqual(upd.run(), 1)
        self.assertTrue(upd.read_result()["rolled_back"])
        self.assertEqual(open(os.path.join(upd.APP, "VERSION")).read().strip(), "1.0.0")

    def test_download_failure_changes_nothing(self):
        def boom(url, limit):
            raise OSError("offline")
        upd.http_get = boom
        self.assertEqual(upd.run(), 1)
        r = upd.read_result()
        self.assertFalse(r["rolled_back"])
        self.assertEqual(open(os.path.join(upd.APP, "marker")).read(), "old")


if __name__ == "__main__":
    unittest.main()
