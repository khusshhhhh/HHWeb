# Local preview server that mirrors the .htaccess rules:
#   /project-glenunga(.html) -> 301 to /projects/glenunga, /blog-x -> /blog/x
#   /projects.html -> 301 to /projects/, /about.html -> /about, /index.html -> /
#   /about -> serves about.html, /projects/ -> serves projects/index.html
import http.server
import os
import re
import sys
from urllib.parse import urlsplit


class CleanURLHandler(http.server.SimpleHTTPRequestHandler):
    def redirect(self, location, query):
        self.send_response(301)
        self.send_header("Location", location + ("?" + query if query else ""))
        self.end_headers()

    def do_GET(self):
        parts = urlsplit(self.path)
        path = parts.path
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
