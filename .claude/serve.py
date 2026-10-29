# Local preview server that mirrors the .htaccess rules:
#   /project-glenunga(.html) -> 301 to /projects/glenunga, /blog-x -> /blog/x
#   /projects.html -> 301 to /projects/, /about.html -> /about, /index.html -> /
#   /about -> serves about.html, /projects/ -> serves projects/index.html
# It also stands in for send.php (no PHP locally): it hands out the same kind of
# signed "not a robot" token, checks it on POST, and prints the email instead of sending it.
import hashlib
import hmac
import http.server
import json
import os
import re
import sys
import time
from urllib.parse import parse_qs, urlsplit

SECRET = b"local-preview"


def sign(t):
    return hmac.new(SECRET, str(t).encode(), hashlib.sha256).hexdigest()


class CleanURLHandler(http.server.SimpleHTTPRequestHandler):
    def json(self, data, code=200):
        body = json.dumps(data).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_POST(self):
        if urlsplit(self.path).path != "/send.php":
            return self.send_error(405)
        n = int(self.headers.get("Content-Length") or 0)
        form = parse_qs(self.rfile.read(n).decode("utf-8", "replace"), keep_blank_values=True)
        f = {k: ", ".join(v) for k, v in form.items()}
        if f.get("website"):
            return self.json({"ok": True, "message": "Thanks."})
        t = int(f.get("hc_t") or 0)
        if not t or not hmac.compare_digest(sign(t), f.get("hc_s", "")):
            return self.json({"ok": False, "message": "Please tick \"I'm not a robot\" and try again."}, 400)
        if time.time() - t < 3:
            return self.json({"ok": False, "message": "That was quick. Please wait a moment and send again."}, 429)
        print("\n===== send.php (local preview, not sent) =====")
        for k, v in f.items():
            if k not in ("hc_t", "hc_s", "website"):
                print(f"{k:>12}: {v}")
        print("===============================================\n", flush=True)
        self.json({"ok": True, "message": "Thanks. Your enquiry is with us."})

    def redirect(self, location, query):
        self.send_response(301)
        self.send_header("Location", location + ("?" + query if query else ""))
        self.end_headers()

    def do_GET(self):
        parts = urlsplit(self.path)
        path = parts.path
        if path == "/send.php":
            t = int(time.time())
            return self.json({"t": t, "s": sign(t)})
        m = re.fullmatch(r"/(projects|blog)\.html", path)
        if m:
            return self.redirect(f"/{m.group(1)}/", parts.query)
        m = re.fullmatch(r"/(project|blog)-([a-z0-9-]+?)(?:\.html)?", path)
        if m:
            folder = "projects" if m.group(1) == "project" else "blog"
            return self.redirect(f"/{folder}/{m.group(2)}", parts.query)
        if path.endswith(".html"):
            clean = path[: -len(".html")]
            if clean.endswith("/index"):
                clean = clean[: -len("index")]
            return self.redirect(clean, parts.query)
        super().do_GET()

    def translate_path(self, path):
        p = super().translate_path(path)
        if not os.path.exists(p) and os.path.isfile(p + ".html"):
            return p + ".html"
        return p


port = int(sys.argv[1]) if len(sys.argv) > 1 else 5173
http.server.ThreadingHTTPServer(("", port), CleanURLHandler).serve_forever()
