/* =========================================================
   Hills & Harbour — interactions
   Lenis smooth scroll + GSAP ScrollTrigger, with plain
   fallbacks. Everything respects prefers-reduced-motion.
   ========================================================= */
(() => {
  const d = document;
  const root = d.documentElement;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fine = matchMedia('(hover: hover) and (pointer: fine)').matches;
  const hasGSAP = !!(window.gsap && window.ScrollTrigger);
  const $ = (s, c = d) => c.querySelector(s);
  const $$ = (s, c = d) => Array.from(c.querySelectorAll(s));
  const lerp = (a, b, t) => a + (b - a) * t;
  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));

  root.classList.remove('no-js');
  root.classList.add('js');
  if (hasGSAP) gsap.registerPlugin(ScrollTrigger);

  /* ---------- Smooth scroll ---------- */
  let lenis = null;
  if (!reduce && window.Lenis) {
    lenis = new Lenis({ duration: 1.15, easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)), smoothWheel: true });
    if (hasGSAP) {
      lenis.on('scroll', ScrollTrigger.update);
      gsap.ticker.add((t) => lenis.raf(t * 1000));
      gsap.ticker.lagSmoothing(0);
    } else {
      const raf = (t) => { lenis.raf(t); requestAnimationFrame(raf); };
      requestAnimationFrame(raf);
    }
  }
  const scrollTo = (target) => {
    if (lenis) lenis.scrollTo(target, { offset: 0, duration: 1.4 });
    else (typeof target === 'number' ? window.scrollTo({ top: target, behavior: reduce ? 'auto' : 'smooth' }) : target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth' }));
  };
  $$('a[href^="#"]').forEach((a) => a.addEventListener('click', (e) => {
    const id = a.getAttribute('href');
    if (id === '#') { e.preventDefault(); scrollTo(0); return; }
    const el = $(id);
    if (el) { e.preventDefault(); scrollTo(el); }
  }));

  /* ---------- Split text into words ---------- */
  $$('[data-split]').forEach((el) => {
    let i = 0;
    const walk = (node) => {
      Array.from(node.childNodes).forEach((child) => {
        if (child.nodeType === 3) {
          const parts = child.textContent.split(/(\s+)/);
          const frag = d.createDocumentFragment();
          parts.forEach((p) => {
            if (!p) return;
            if (/^\s+$/.test(p)) { frag.appendChild(d.createTextNode(' ')); return; }
            const w = d.createElement('span');
            w.className = 'split-word';
            w.innerHTML = '<span style="--i:' + i++ + '"></span>';
            w.firstChild.textContent = p;
            frag.appendChild(w);
          });
          child.replaceWith(frag);
        } else if (child.nodeType === 1 && child.tagName !== 'BR') {
          walk(child);
        }
      });
    };
    walk(el);
    el.setAttribute('aria-label', el.textContent.replace(/\s+/g, ' ').trim());
  });

  /* ---------- Scroll-filled statement ---------- */
  const fillEls = $$('[data-fill]');
  fillEls.forEach((el) => {
    el.innerHTML = el.textContent.trim().split(/\s+/).map((w) => '<span class="fw">' + w + '</span>').join(' ');
  });
  const updateFill = () => {
    const vh = innerHeight;
    fillEls.forEach((el) => {
      const r = el.getBoundingClientRect();
      const p = clamp((vh * 0.85 - r.top) / (r.height + vh * 0.35), 0, 1);
      const words = el.children;
      const n = Math.round(p * words.length);
      for (let i = 0; i < words.length; i++) words[i].classList.toggle('on', i < n || reduce);
    });
  };

  /* ---------- Ready gate (loader / page transition) ---------- */
  let readyFired = false;
  const readyQueue = [];
  const onReady = (fn) => (readyFired ? fn() : readyQueue.push(fn));
  const fireReady = () => { if (readyFired) return; readyFired = true; readyQueue.forEach((fn) => fn()); };

  const loader = $('.loader');
  if (root.classList.contains('is-loading') && loader && !reduce) {
    if (lenis) lenis.stop();
    const count = $('.loader__count', loader);
    const bar = $('.loader__bar', loader);
    const a = $('.reveal-a', loader);
    const b = $('.reveal-b', loader);
    const dur = 1700;
    const t0 = performance.now();
    const tick = (now) => {
      const p = clamp((now - t0) / dur, 0, 1);
      const e = 1 - Math.pow(1 - p, 3);
      if (count) count.textContent = String(Math.round(e * 100)).padStart(3, '0');
      if (bar) bar.style.transform = 'scaleX(' + e + ')';
      const pa = clamp(e * 1.6, 0, 1), pb = clamp(e * 1.6 - 0.45, 0, 1);
      if (a) a.style.clipPath = 'inset(0 ' + (100 - pa * 100) + '% 50% 0)';
      if (b) b.style.clipPath = 'inset(50% ' + (100 - pb * 100) + '% 0 0)';
      if (p < 1) return requestAnimationFrame(tick);
      setTimeout(() => {
        const anim = loader.animate([{ clipPath: 'inset(0 0 0 0)' }, { clipPath: 'inset(0 0 100% 0)' }], { duration: 1000, easing: 'cubic-bezier(.65,.05,.36,1)', fill: 'forwards' });
        setTimeout(fireReady, 380);
        anim.onfinish = () => {
          root.classList.remove('is-loading');
          try { sessionStorage.setItem('hh-loaded', '1'); } catch (_) {}
          if (lenis) lenis.start();
        };
      }, 250);
    };
    requestAnimationFrame(tick);
  } else {
    root.classList.remove('is-loading');
    try { sessionStorage.setItem('hh-loaded', '1'); } catch (_) {}
  }

  const pt = $('.pt');
  if (root.classList.contains('pt-in') && pt) {
    const anim = pt.animate([{ transform: 'translateY(0)' }, { transform: 'translateY(-100%)' }], { duration: reduce ? 1 : 900, easing: 'cubic-bezier(.65,.05,.36,1)', delay: 120, fill: 'forwards' });
    setTimeout(fireReady, reduce ? 0 : 450);
    anim.onfinish = () => { root.classList.remove('pt-in'); anim.cancel(); };
  } else if (!root.classList.contains('is-loading') || reduce) {
    requestAnimationFrame(fireReady);
    setTimeout(fireReady, 120);
  }

  // Page transition out
  d.addEventListener('click', (e) => {
    const a = e.target.closest('a');
    if (!a || !pt || reduce) return;
    const href = a.getAttribute('href') || '';
    if (a.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    if (!href || href.startsWith('#') || /^(mailto:|tel:|https?:)/.test(href)) return;
    if (a.hasAttribute('download')) return;
    e.preventDefault();
    try { sessionStorage.setItem('hh-pt', '1'); } catch (_) {}
    pt.classList.add('is-active');
    pt.animate([{ transform: 'translateY(100%)' }, { transform: 'translateY(0)' }], { duration: 750, easing: 'cubic-bezier(.65,.05,.36,1)', fill: 'forwards' })
      .onfinish = () => { window.location.href = href; };
  });
  window.addEventListener('pageshow', (e) => {
    if (e.persisted && pt) { pt.getAnimations().forEach((an) => an.cancel()); pt.classList.remove('is-active'); root.classList.remove('pt-in'); }
  });

  /* ---------- Reveal on view ---------- */
  const revealSel = '[data-reveal], [data-split], .hl, .step-row, .tl-step, .drawing-panel';
  const io = new IntersectionObserver((entries) => {
    entries.forEach((en) => {
      if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
  onReady(() => {
    // First-screen heroes play straight away; everything else waits for the viewport
    const heroes = $$('.hero, .page-hero, .proj-hero');
    $$(revealSel).forEach((el) => (heroes.some((h) => h.contains(el)) ? el.classList.add('is-in') : io.observe(el)));
  });

  // Colour returns to images on touch devices once in view
  if (!fine) {
    const ioc = new IntersectionObserver((entries) => entries.forEach((en) => en.target.classList.toggle('is-colour', en.isIntersecting)), { rootMargin: '-35% 0px -35% 0px' });
    $$('.mono-img').forEach((el) => ioc.observe(el));
  }

  /* ---------- Header ---------- */
  const header = $('.site-header');
  let lastY = 0;
  const updateHeader = (y) => {
    if (!header) return;
    const menuOpen = d.body.classList.contains('menu-open');
    header.classList.toggle('is-scrolled', y > 40);
    if (!menuOpen) header.classList.toggle('is-hidden', y > lastY && y > 320);
    lastY = y;
    const probe = d.elementsFromPoint(innerWidth / 2, header.offsetHeight / 2).find((el) => !header.contains(el) && el.closest('[data-header]'));
    const zone = probe ? probe.closest('[data-header]') : null;
    header.classList.toggle('is-light', !!zone && zone.dataset.header === 'light');
  };
  header && header.addEventListener('focusin', () => header.classList.remove('is-hidden'));

  // Mobile menu
  const burger = $('.burger');
  burger && burger.addEventListener('click', () => {
    const open = !d.body.classList.contains('menu-open');
    d.body.classList.toggle('menu-open', open);
    burger.setAttribute('aria-expanded', String(open));
    $$('.mobile-menu__list a').forEach((a, i) => (a.style.transitionDelay = open ? 250 + i * 60 + 'ms' : '0ms'));
    if (lenis) open ? lenis.stop() : lenis.start();
  });
  d.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && d.body.classList.contains('menu-open')) burger.click();
  });

  // Dropdown on touch / keyboard
  $$('.nav__item--drop').forEach((item) => {
    const btn = $('.nav__link', item);
    btn.addEventListener('click', (e) => {
      if (!fine) { e.preventDefault(); item.classList.toggle('is-open'); }
    });
    btn.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') { e.preventDefault(); item.classList.add('is-open'); $('.dropdown a', item).focus(); }
    });
    item.addEventListener('focusout', (e) => { if (!item.contains(e.relatedTarget)) item.classList.remove('is-open'); });
  });

  /* ---------- Unified scroll loop ---------- */
  const progress = $('.scroll-progress span');
  const onScroll = () => {
    const y = lenis ? lenis.scroll : window.scrollY;
    updateHeader(y);
    updateFill();
    if (progress) progress.style.transform = 'scaleX(' + clamp(y / Math.max(1, root.scrollHeight - innerHeight), 0, 1) + ')';
  };
  if (lenis) lenis.on('scroll', onScroll);
  else addEventListener('scroll', () => requestAnimationFrame(onScroll), { passive: true });
  addEventListener('resize', onScroll);
  onScroll();

  /* ---------- Hero stage cycler: Land → Build → Handover ---------- */
  const cycle = $('[data-stage-cycle]');
  if (cycle) {
    const imgs = $$('.hero__stage-frame img', cycle);
    const bars = $$('.hero__stage-bars i', cycle);
    const label = $('[data-stage-label]', cycle);
    const words = $$('[data-stage-word]');
    const names = ['01 · Land', '02 · Build', '03 · Handover'];
    let cur = 0;
    const show = (n) => {
      imgs.forEach((im, k) => {
        im.classList.toggle('is-leaving', k === cur && n !== cur);
        im.classList.toggle('is-active', k === n);
      });
      cur = n;
      if (label) label.textContent = names[n];
      words.forEach((w, k) => w.classList.toggle('is-on', k === n));
      bars.forEach((b, k) => { b.getAnimations().forEach((a) => a.cancel()); b.style.transform = k < n ? 'scaleX(1)' : 'scaleX(0)'; });
      if (reduce) { bars.forEach((b) => (b.style.transform = 'scaleX(1)')); return; }
      bars[n].animate([{ transform: 'scaleX(0)' }, { transform: 'scaleX(1)' }], { duration: 3800, easing: 'linear', fill: 'forwards' })
        .onfinish = () => show((n + 1) % imgs.length);
    };
    onReady(() => show(0));
  }

  /* ---------- Live Adelaide clock & year ---------- */
  const clockEls = $$('[data-clock]');
  if (clockEls.length) {
    const fmt = new Intl.DateTimeFormat('en-AU', { timeZone: 'Australia/Adelaide', hour: '2-digit', minute: '2-digit', hour12: false, timeZoneName: 'short' });
    const tickClock = () => clockEls.forEach((el) => (el.textContent = fmt.format(new Date()).toUpperCase()));
    tickClock();
    setInterval(tickClock, 20000);
  }
  $$('[data-year]').forEach((el) => (el.textContent = new Date().getFullYear()));

  /* ---------- Counters ---------- */
  const ioCount = new IntersectionObserver((entries) => {
    entries.forEach((en) => {
      if (!en.isIntersecting) return;
      const el = en.target;
      ioCount.unobserve(el);
      const to = parseFloat(el.dataset.count);
      if (reduce) { el.textContent = to.toLocaleString('en-AU'); return; }
      const t0 = performance.now(), dur = 1800;
      const step = (now) => {
        const p = clamp((now - t0) / dur, 0, 1);
        el.textContent = Math.round(to * (1 - Math.pow(1 - p, 4))).toLocaleString('en-AU');
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  }, { threshold: 0.6 });
  $$('[data-count]').forEach((el) => ioCount.observe(el));

  /* ---------- Cursor ---------- */
  const cursor = $('.cursor');
  if (cursor && fine && !reduce) {
    const dot = $('.cursor__dot', cursor);
    const label = $('span', dot);
    let mx = innerWidth / 2, my = innerHeight / 2, cx = mx, cy = my, sc = 1, lastScroll = scrollY;
    addEventListener('pointermove', (e) => { mx = e.clientX; my = e.clientY; }, { passive: true });
    const loop = () => {
      cx = lerp(cx, mx, 0.2); cy = lerp(cy, my, 0.2);
      cursor.style.transform = 'translate3d(' + cx + 'px,' + cy + 'px,0)';
      // The square swells while the page is scrolling, then settles back
      const v = lenis ? Math.abs(lenis.velocity) : Math.abs(scrollY - lastScroll);
      lastScroll = scrollY;
      sc = lerp(sc, 1 + Math.min(v * 0.06, 0.9), 0.15);
      dot.style.setProperty('--s', sc.toFixed(3));
      requestAnimationFrame(loop);
    };
    loop();
    d.addEventListener('pointerover', (e) => {
      const lab = e.target.closest('[data-cursor]');
      const link = e.target.closest('a, button, input, textarea, label');
      cursor.classList.toggle('is-label', !!lab);
      cursor.classList.toggle('is-link', !lab && !!link);
      if (lab) label.textContent = lab.dataset.cursor;
    });
    d.addEventListener('pointerleave', () => (cursor.style.opacity = 0));
    d.addEventListener('pointerenter', () => (cursor.style.opacity = 1));
  }

  /* ---------- Magnetic buttons ---------- */
  if (fine && !reduce) {
    $$('[data-magnetic]').forEach((el) => {
      el.addEventListener('pointermove', (e) => {
        const r = el.getBoundingClientRect();
        const x = (e.clientX - r.left - r.width / 2) * 0.25;
        const y = (e.clientY - r.top - r.height / 2) * 0.35;
        el.style.transform = 'translate(' + x + 'px,' + y + 'px)';
      });
      el.addEventListener('pointerleave', () => {
        el.style.transition = 'transform 600ms cubic-bezier(.16,1,.3,1)';
        el.style.transform = '';
        setTimeout(() => (el.style.transition = ''), 600);
      });
    });
  }

  /* ---------- Service rows: image follows cursor ---------- */
  const hr = $('.hover-reveal');
  if (hr && fine && !reduce) {
    const imgs = $$('img', hr);
    let tx = 0, ty = 0, x = 0, y = 0, on = false, raf = null;
    const move = () => {
      x = lerp(x, tx, 0.12); y = lerp(y, ty, 0.12);
      const rot = clamp((tx - x) * 0.04, -8, 8);
      hr.style.transform = 'translate(' + (x - hr.offsetWidth / 2) + 'px,' + (y - hr.offsetHeight / 2) + 'px) rotate(' + rot + 'deg) scale(' + (on ? 1 : 0.85) + ')';
      raf = requestAnimationFrame(move);
    };
    $$('[data-hover-img]').forEach((row) => {
      row.addEventListener('pointerenter', (e) => {
        if (!on) { x = tx = e.clientX; y = ty = e.clientY; }
        on = true; hr.classList.add('is-on');
        imgs.forEach((im, i) => im.classList.toggle('is-active', i === +row.dataset.hoverImg));
        if (!raf) move();
      });
      row.addEventListener('pointermove', (e) => { tx = e.clientX; ty = e.clientY; });
      row.addEventListener('pointerleave', () => { on = false; hr.classList.remove('is-on'); });
    });
  }

  /* ---------- Marquee (scroll-velocity driven) ---------- */
  $$('.marquee').forEach((mq) => {
    const track = $('.marquee__track', mq);
    if (!track) return;
    mq.appendChild(track.cloneNode(true)).setAttribute('aria-hidden', 'true');
    if (reduce) return;
    const tracks = $$('.marquee__track', mq);
    let pos = 0, dir = -1, skew = 0;
    const loop = () => {
      const v = lenis ? lenis.velocity : 0;
      if (v > 0.5) dir = -1; else if (v < -0.5) dir = 1;
      pos += dir * (0.6 + Math.min(Math.abs(v) * 0.35, 14));
      const w = track.offsetWidth;
      if (pos <= -w) pos += w;
      if (pos > 0) pos -= w;
      skew = lerp(skew, clamp(v * -0.25, -8, 8), 0.1);
      tracks.forEach((t) => (t.style.transform = 'translate3d(' + pos + 'px,0,0) skewX(' + skew + 'deg)'));
      requestAnimationFrame(loop);
    };
    loop();
  });

  /* ---------- Accordion ---------- */
  $$('.acc-item').forEach((item) => {
    const btn = $('.acc-btn', item);
    const panel = $('.acc-panel', item);
    btn.addEventListener('click', () => {
      const open = !item.classList.contains('is-open');
      item.classList.toggle('is-open', open);
      btn.setAttribute('aria-expanded', String(open));
      panel.style.height = panel.scrollHeight + 'px';
      if (!open) requestAnimationFrame(() => (panel.style.height = '0px'));
      else panel.addEventListener('transitionend', () => { if (item.classList.contains('is-open')) panel.style.height = 'auto'; }, { once: true });
      if (hasGSAP) setTimeout(() => ScrollTrigger.refresh(), 650);
    });
  });

  /* ---------- Project filter ---------- */
  const grid = $('.project-grid');
  if (grid) {
    const cards = $$('.project-card', grid);
    const countEl = $('[data-view-count]');
    $$('.filter-btn').forEach((btn) => btn.addEventListener('click', () => {
      $$('.filter-btn').forEach((b) => { b.classList.toggle('is-active', b === btn); b.setAttribute('aria-pressed', String(b === btn)); });
      const f = btn.dataset.filter;
      cards.forEach((c) => { c.style.opacity = 0; c.style.transform = 'translateY(24px)'; });
      setTimeout(() => {
        let n = 0;
        cards.forEach((c) => {
          const show = f === 'all' || c.dataset.cat === f;
          c.classList.toggle('is-hidden', !show);
          if (show) n++;
        });
        grid.classList.toggle('is-filtered', f !== 'all');
        if (countEl) countEl.textContent = String(n).padStart(2, '0');
        requestAnimationFrame(() => cards.forEach((c, i) => setTimeout(() => { c.style.opacity = 1; c.style.transform = 'none'; }, i * 70)));
        if (hasGSAP) ScrollTrigger.refresh();
      }, 450);
    }));
  }

  /* ---------- Lightbox ---------- */
  const lb = $('.lightbox');
  if (lb) {
    const items = $$('[data-lightbox]');
    const img = $('img', lb);
    const cap = $('[data-lb-caption]', lb);
    const num = $('[data-lb-count]', lb);
    let idx = 0, lastFocus = null;
    const show = (i) => {
      idx = (i + items.length) % items.length;
      img.src = items[idx].dataset.lightbox;
      img.alt = items[idx].dataset.caption || '';
      cap.textContent = items[idx].dataset.caption || '';
      num.textContent = String(idx + 1).padStart(2, '0') + ' / ' + String(items.length).padStart(2, '0');
    };
    const open = (i) => { lastFocus = d.activeElement; show(i); lb.classList.add('is-open'); lb.setAttribute('aria-hidden', 'false'); $('[data-lb-close]', lb).focus(); if (lenis) lenis.stop(); };
    const close = () => { lb.classList.remove('is-open'); lb.setAttribute('aria-hidden', 'true'); if (lenis) lenis.start(); lastFocus && lastFocus.focus(); };
    items.forEach((it, i) => it.addEventListener('click', () => open(i)));
    $('[data-lb-close]', lb).addEventListener('click', close);
    $('[data-lb-prev]', lb).addEventListener('click', () => show(idx - 1));
    $('[data-lb-next]', lb).addEventListener('click', () => show(idx + 1));
    lb.addEventListener('click', (e) => { if (e.target === lb) close(); });
    d.addEventListener('keydown', (e) => {
      if (!lb.classList.contains('is-open')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') show(idx - 1);
      if (e.key === 'ArrowRight') show(idx + 1);
    });
  }

  /* ---------- Copy to clipboard ---------- */
  $$('[data-copy]').forEach((btn) => btn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(btn.dataset.copy);
      const t = btn.textContent;
      btn.textContent = 'Copied ✓';
      btn.classList.add('is-done');
      setTimeout(() => { btn.textContent = t; btn.classList.remove('is-done'); }, 1800);
    } catch (_) {}
  }));

  /* ---------- Contact form → email draft ---------- */
  const form = $('#enquiry-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      let ok = true;
      $$('[required]', form).forEach((input) => {
        const field = input.closest('.field');
        const valid = input.type === 'email' ? /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim()) : input.value.trim().length > 1;
        field && field.classList.toggle('has-error', !valid);
        if (!valid) ok = false;
      });
      if (!ok) { const first = $('.has-error input, .has-error textarea', form); first && first.focus(); return; }
      const data = new FormData(form);
      const types = data.getAll('type').join(', ') || 'General enquiry';
      const body = [
        'Name: ' + data.get('name'),
        'Email: ' + data.get('email'),
        'Phone: ' + (data.get('phone') || '—'),
        'Suburb / site: ' + (data.get('suburb') || '—'),
        'Interested in: ' + types,
        '',
        data.get('message'),
      ].join('\n');
      window.location.href = 'mailto:admin@hillsandharbour.com.au?subject=' + encodeURIComponent('Project enquiry · ' + types) + '&body=' + encodeURIComponent(body);
      form.classList.add('is-sent');
      if (hasGSAP) ScrollTrigger.refresh();
    });
    $$('input, textarea', form).forEach((i) => i.addEventListener('input', () => i.closest('.field') && i.closest('.field').classList.remove('has-error')));
  }

  /* ---------- Dot-matrix map of metropolitan Adelaide, Broadview highlighted ---------- */
  const dotmap = $('.dotmap canvas');
  if (dotmap) {
    // Roughly 24 km square: Gulf St Vincent to the west, the Adelaide Hills to the east
    const W = 138.4, E = 138.74, N = -34.78, S = -35.06;
    const LAND = [[138.74,-34.78],[138.481,-34.78],[138.476,-34.81],[138.48,-34.84],[138.488,-34.88],[138.493,-34.91],[138.503,-34.95],[138.512,-34.98],[138.516,-35.0],[138.52,-35.03],[138.508,-35.06],[138.74,-35.06]];
    const HILLS = [[138.74,-34.78],[138.70,-34.78],[138.69,-34.83],[138.67,-34.88],[138.655,-34.93],[138.64,-34.97],[138.625,-35.0],[138.615,-35.06],[138.74,-35.06]];
    const CBD = [[138.587,-34.917],[138.614,-34.917],[138.614,-34.937],[138.587,-34.937]];
    const BROADVIEW = [[138.604,-34.869],[138.625,-34.869],[138.625,-34.885],[138.604,-34.885]];
    const FOCUS = [138.6145, -34.877];
    const inPoly = (x, y, poly) => {
      let inside = false;
      for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
        const [xi, yi] = poly[i], [xj, yj] = poly[j];
        if ((yi > y) !== (yj > y) && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) inside = !inside;
      }
      return inside;
    };
    const ctx = dotmap.getContext('2d');
    const pins = $$('.dotmap__pin[data-lon]');
    let dots = [], size = 0, start = null, focus = [0, 0], rDot = 1.5;
    const project = (lon, lat) => [((lon - W) / (E - W)) * size, ((N - lat) / (N - S)) * size];
    const build = () => {
      size = dotmap.getBoundingClientRect().width;
      const dpr = Math.min(devicePixelRatio || 1, 2);
      dotmap.width = size * dpr; dotmap.height = size * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      focus = project(FOCUS[0], FOCUS[1]);
      rDot = Math.max(1.3, size / 300);
      dots = [];
      const step = Math.max(8, size / 58);
      for (let y = step / 2; y < size; y += step) {
        for (let x = step / 2; x < size; x += step) {
          const lon = W + (x / size) * (E - W);
          const lat = N - (y / size) * (N - S);
          if (!inPoly(lon, lat, LAND)) continue;
          let c = '#3c3c3c', r = rDot;
          if (inPoly(lon, lat, HILLS)) c = '#6b6b6b';
          if (inPoly(lon, lat, CBD)) c = '#bdbdbd';
          if (inPoly(lon, lat, BROADVIEW)) { c = '#2fd27a'; r = rDot * 1.6; }
          dots.push([x, y, Math.hypot(x - focus[0], y - focus[1]), c, r]);
        }
      }
      pins.forEach((pin) => {
        const [px, py] = project(+pin.dataset.lon, +pin.dataset.lat);
        pin.style.left = (px / size) * 100 + '%';
        pin.style.top = (py / size) * 100 + '%';
      });
    };
    const draw = (now) => {
      if (start === null) start = now;
      const t = reduce ? 1e9 : now - start;
      ctx.clearRect(0, 0, size, size);
      const reach = t * 0.55;
      dots.forEach(([x, y, dist, c, r]) => {
        if (dist > reach) return;
        ctx.fillStyle = c;
        ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill();
      });
      const pulse = (t % 2400) / 2400;
      ctx.strokeStyle = 'rgba(47,210,122,' + (1 - pulse) + ')';
      ctx.lineWidth = 1.5;
      ctx.beginPath(); ctx.arc(focus[0], focus[1], 26 + pulse * 30, 0, Math.PI * 2); ctx.stroke();
      requestAnimationFrame(draw);
    };
    build();
    addEventListener('resize', build);
    const iom = new IntersectionObserver((en) => { if (en[0].isIntersecting) { iom.disconnect(); requestAnimationFrame(draw); } }, { threshold: 0.3 });
    iom.observe(dotmap);
  }

  /* =========================================================
     GSAP scroll scenes
     ========================================================= */
  if (!hasGSAP || reduce) return;

  const mm = gsap.matchMedia();

  // Parallax images
  $$('[data-parallax]').forEach((el) => {
    const img = $('img', el) || el;
    const amt = parseFloat(el.dataset.parallax) || 12;
    gsap.fromTo(img, { yPercent: -amt }, { yPercent: amt, ease: 'none', scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true } });
  });

  // Hero: drift title and hills on scroll
  const hero = $('.hero');
  if (hero) {
    gsap.to('.hero__title', { yPercent: -18, opacity: 0.25, ease: 'none', scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: true } });
    gsap.to('.hero__hills', { yPercent: 25, ease: 'none', scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: true } });
    const hills = $$('.hero__hills path');
    hills.forEach((p) => { const L = p.getTotalLength(); p.style.strokeDasharray = L; p.style.strokeDashoffset = L; });
    onReady(() => gsap.to(hills, { strokeDashoffset: 0, duration: 2.6, ease: 'power3.inOut', stagger: 0.18, delay: 0.3 }));
  }

  // Right block, right build: the block is pegged out, the house rises from the ground line, then fills the screen
  const expand = $('.expand');
  if (expand) {
    const box = () => {
      const cs = getComputedStyle(expand);
      return { t: cs.getPropertyValue('--bt').trim(), x: cs.getPropertyValue('--bx').trim(), b: cs.getPropertyValue('--bb').trim() };
    };
    const ground = () => { const v = box(); return 'inset(' + (100 - parseFloat(v.b)) + '% ' + v.x + ' ' + v.b + ' ' + v.x + ')'; };
    const plot = () => { const v = box(); return 'inset(' + v.t + ' ' + v.x + ' ' + v.b + ' ' + v.x + ')'; };
    const stage = $('[data-expand-stage]');
    const setStage = (s) => { if (stage && stage.textContent !== s) stage.textContent = s; };
    const tl = gsap.timeline({
      defaults: { ease: 'none' },
      scrollTrigger: {
        trigger: expand, start: 'top top', end: '+=220%', scrub: 0.8, pin: true, anticipatePin: 1, invalidateOnRefresh: true,
        onUpdate: (st) => setStage(st.progress < 0.3 ? 'Site' : st.progress < 0.64 ? 'Build' : 'Handover'),
      },
    });
    tl.fromTo('.expand__ground', { scaleX: 0 }, { scaleX: 1, duration: 0.12 }, 0)
      .fromTo('.expand__words', { opacity: 0, yPercent: 40 }, { opacity: 1, yPercent: 0, duration: 0.08 }, 0.04)
      .fromTo('.expand__plot rect', { strokeDashoffset: 1 }, { strokeDashoffset: 0, duration: 0.16 }, 0.04)
      .fromTo('.expand__peg', { scale: 0 }, { scale: 1, duration: 0.04, stagger: 0.025 }, 0.12)
      .fromTo(['.expand__dim', '.expand__tag'], { opacity: 0, y: 8 }, { opacity: 1, y: 0, duration: 0.06 }, 0.14)
      // the build rises from the ground line inside the block
      .fromTo('.expand__frame', { clipPath: ground }, { clipPath: plot, duration: 0.3, ease: 'power1.inOut' }, 0.26)
      .fromTo('.expand__frame img', { yPercent: 18, scale: 1.18 }, { yPercent: 0, scale: 1.08, duration: 0.3, ease: 'power1.inOut' }, 0.26)
      .to('.expand__roll-inner', { yPercent: -50, duration: 0.07, ease: 'power2.inOut' }, 0.4)
      // then it takes over the screen
      .fromTo('.expand__frame', { clipPath: plot }, { clipPath: 'inset(0% 0% 0% 0%)', duration: 0.26, ease: 'power2.in', immediateRender: false }, 0.66)
      .to('.expand__frame img', { scale: 1, duration: 0.26 }, 0.66)
      .to(['.expand__plot', '.expand__ground'], { opacity: 0, scale: 1.08, duration: 0.14 }, 0.66)
      .to('.expand__words', { opacity: 0, yPercent: -30, duration: 0.1 }, 0.72)
      .fromTo('.expand__copy', { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: 0.1 }, 0.88);
  }

  // Build sequence (Land → Frame → Roof → Handover)
  const build = $('.build');
  if (build) {
    const stages = $$('.build__stage', build);
    const counter = $('.build__counter b', build);
    const bar = $('.build__progress span', build);
    const makeTl = () => {
      // Each stage gets exactly one unit of timeline, so scroll progress maps 1:1 to the stage list
      const tl = gsap.timeline();
      stages.forEach((_, k) => {
        const g = $('.s' + (k + 1), build);
        if (!g) return;
        const lines = $$('.ln', g);
        const covers = $$('.fill-black', g);
        if (covers.length) tl.to(covers, { opacity: 1, duration: 0.35, ease: 'none' }, k);
        tl.to(lines, { strokeDashoffset: 0, duration: 0.6, stagger: Math.min(0.05, 0.25 / lines.length), ease: 'power2.inOut' }, k + 0.05);
        const greens = $$('.fill-green', g);
        if (greens.length) tl.to(greens, { opacity: 1, duration: 0.2 }, k + 0.7);
        const lit = $$('.fill-lit', g);
        if (lit.length) tl.to(lit, { opacity: 0.14, duration: 0.2, stagger: 0.03 }, k + 0.6);
        tl.set({}, {}, k + 1);
      });
      return tl;
    };
    const setStage = (p) => {
      const n = stages.length;
      const i = Math.min(n - 1, Math.floor(p * n));
      stages.forEach((s, k) => s.classList.toggle('is-active', k === i));
      if (counter) counter.textContent = '0' + (i + 1);
      if (bar) bar.style.transform = 'scaleX(' + p + ')';
    };
    mm.add('(min-width: 901px)', () => {
      const tl = makeTl();
      ScrollTrigger.create({
        trigger: build, start: 'top top', end: '+=520%', pin: true, scrub: 1, animation: tl, anticipatePin: 1,
        onUpdate: (self) => setStage(self.progress),
      });
      setStage(0);
    });
    mm.add('(max-width: 900px)', () => {
      const tl = makeTl();
      ScrollTrigger.create({ trigger: build, start: 'top 75%', end: 'bottom 60%', scrub: 1, animation: tl });
    });
  }

  // Featured media zoom-out
  $$('[data-zoom]').forEach((el) => {
    if ($('img', el)) gsap.fromTo($('img', el), { scale: 1.3 }, { scale: 1, ease: 'none', scrollTrigger: { trigger: el, start: 'top bottom', end: 'center center', scrub: true } });
    gsap.fromTo(el, { clipPath: 'inset(12% 8% 12% 8%)' }, { clipPath: 'inset(0% 0% 0% 0%)', ease: 'none', scrollTrigger: { trigger: el, start: 'top bottom', end: 'center center', scrub: true } });
  });

  // Timeline fill
  $$('.timeline').forEach((tlEl) => {
    const fill = $('.timeline__fill', tlEl);
    if (!fill) return;
    mm.add('(min-width: 1101px)', () => gsap.fromTo(fill, { scaleX: 0 }, { scaleX: 1, ease: 'none', scrollTrigger: { trigger: tlEl, start: 'top 80%', end: 'bottom 55%', scrub: true } }));
    mm.add('(max-width: 1100px)', () => gsap.fromTo(fill, { scaleY: 0 }, { scaleY: 1, ease: 'none', scrollTrigger: { trigger: tlEl, start: 'top 75%', end: 'bottom 50%', scrub: true } }));
  });

  // Horizontal gallery
  const hs = $('.hscroll');
  if (hs) {
    mm.add('(min-width: 901px)', () => {
      const track = $('.hscroll__track', hs);
      const bar = $('.hscroll__bar span', hs);
      const dist = () => Math.max(0, track.scrollWidth - innerWidth);
      gsap.to(track, {
        x: () => -dist(), ease: 'none',
        scrollTrigger: { trigger: hs, start: 'top top', end: () => '+=' + dist(), pin: true, scrub: 1, invalidateOnRefresh: true, anticipatePin: 1, onUpdate: (s) => bar && (bar.style.transform = 'scaleX(' + s.progress + ')') },
      });
    });
  }

  // Giant footer logo rises in
  const giant = $('.footer__giant img');
  if (giant) gsap.fromTo(giant, { yPercent: 45, scaleY: 0.7 }, { yPercent: 0, scaleY: 1, ease: 'none', scrollTrigger: { trigger: '.site-footer', start: 'top bottom', end: 'bottom bottom', scrub: true } });

  // Big outline index on inner heroes
  $$('.page-hero__index').forEach((el) => gsap.to(el, { yPercent: 40, ease: 'none', scrollTrigger: { trigger: el.parentElement, start: 'top top', end: 'bottom top', scrub: true } }));

  addEventListener('load', () => ScrollTrigger.refresh());
})();
