# Hills & Harbour website

Static site for [hillsandharbour.com.au](https://hillsandharbour.com.au). Plain HTML, CSS and JavaScript (GSAP, ScrollTrigger and Lenis from a CDN), with no build step.

## Structure

- `index.html`, `about.html`, `contact.html`, `custom-houses.html`, `development-projects.html`: top-level pages
- `projects/`: project index and one page per project
- `blog/`: blog index and articles (linked from the footer only)
- `assets/`: CSS, JavaScript and images
- `send.php`: emails the contact and development enquiry forms to admin@hillsandharbour.com.au, with a server-checked "I'm not a robot" token, a honeypot field and a per-IP limit
- `.htaccess`: Apache clean URLs (`/about` serves `about.html`, `.html` URLs redirect to clean ones, old flat URLs redirect into the folders)
- `sitemap.xml`, `robots.txt`

## Local preview

Pages link to each other with relative `.html` paths, so the site works opened straight from the folder or from any static server.

To preview with the same clean URLs as the live server:

```bash
python .claude/serve.py 5173
```

Then open http://localhost:5173. The preview server has no PHP, so it stands in for `send.php`: forms work end to end and each enquiry is printed in the terminal instead of being emailed.

## Deploying

Upload everything except `.claude/`, `README.md` and `.gitignore` to the web root of an Apache host, including `.htaccess`.

The forms need PHP with `mail()` (standard on cPanel). Before going live:

- Make sure `no-reply@hillsandharbour.com.au` may send for the domain (SPF record), or change `FROM_EMAIL`.
- If the domain's email is hosted elsewhere (Google Workspace, Microsoft 365), set cPanel's Email Routing to "Remote Mail Exchanger" so mail to admin@ leaves the server.
- Send a test enquiry from the live contact page.
