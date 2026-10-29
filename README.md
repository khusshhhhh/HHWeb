# Hills & Harbour website

Static site for [hillsandharbour.com.au](https://hillsandharbour.com.au). Plain HTML, CSS and JavaScript (GSAP, ScrollTrigger and Lenis from a CDN), with no build step.

## Structure

- `index.html`, `about.html`, `contact.html`, `custom-houses.html`, `development-projects.html`: top-level pages
- `projects/`: project index and one page per project
- `blog/`: blog index and articles (linked from the footer only)
- `assets/`: CSS, JavaScript and images
- `.htaccess`: Apache clean URLs (`/about` serves `about.html`, `.html` URLs redirect to clean ones, old flat URLs redirect into the folders)
- `sitemap.xml`, `robots.txt`

## Local preview

Pages link to each other with relative `.html` paths, so the site works opened straight from the folder or from any static server.

To preview with the same clean URLs as the live server:

```bash
python .claude/serve.py 5173
```

Then open http://localhost:5173.

## Deploying

Upload everything except `.claude/`, `README.md` and `.gitignore` to the web root of an Apache host, including `.htaccess`.
