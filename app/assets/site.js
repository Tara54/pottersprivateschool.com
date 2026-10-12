/* Potters School - interactions du site public. Cembè Conseil & Services */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };

  /* ---------- Langue FR / EN sans rechargement ---------- */
  var cur = document.documentElement.lang === 'en' ? 'en' : 'fr';
  var T = {
    fr: { err: "Renseignez les champs demandés.", tel: "Indiquez un numéro de téléphone valide.",
      errSend: "L'envoi n'a pas abouti. Réessayez ou écrivez-nous sur WhatsApp.",
      errLimit: "Trop de demandes envoyées. Appelez l'école ou écrivez sur WhatsApp.",
      errTok: "La page est restée ouverte trop longtemps. Rechargez-la puis réessayez.",
      recap: ["Enfant", "Âge", "Classe", "Parent", "Téléphone", "Message"],
      wa: "Bonjour Potters School, je souhaite pré-inscrire mon enfant." },
    en: { err: "Please fill in the required fields.", tel: "Please enter a valid phone number.",
      errSend: "Sending failed. Try again or message us on WhatsApp.",
      errLimit: "Too many requests sent. Please call the school or use WhatsApp.",
      errTok: "This page was open too long. Please reload it and try again.",
      recap: ["Child", "Age", "Class", "Parent", "Phone", "Message"],
      wa: "Hello Potters School, I would like to pre-register my child." }
  };
  var langBtn = $('#lang');
  function setLang(l) {
    cur = l;
    $$('[data-fr][data-en]').forEach(function (el) { el.innerHTML = el.getAttribute('data-' + l); });
    ['alt', 'placeholder', 'aria-label'].forEach(function (a) {
      $$('[data-' + a + '-' + l + ']').forEach(function (el) { el.setAttribute(a, el.getAttribute('data-' + a + '-' + l)); });
    });
    document.documentElement.lang = l;
    document.title = document.body.getAttribute('data-title-' + l) || document.title;
    langBtn.textContent = l === 'en' ? 'FR' : 'EN';
    langBtn.setAttribute('href', l === 'en' ? './' : '?lang=en');
    try { history.replaceState(null, '', (l === 'en' ? '?lang=en' : location.pathname) + location.hash); } catch (e) {}
    $$('.rot-w').forEach(function (b) { if (b._reset) b._reset(); });
    if (errKey) showErr(errKey);
    if ($('.step.on[data-step="3"]')) buildRecap();
    store.set('ps-lang', l);
  }
  langBtn.addEventListener('click', function (e) { e.preventDefault(); setLang(cur === 'fr' ? 'en' : 'fr'); });

  /* ---------- En-tête, menu mobile, progression, retour en haut ---------- */
  var top = $('#top'), bar = $('#progress'), toTop = $('#toTop'), menu = $('#menu'), burger = $('#burger');
  function closeMenu() { menu.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); }
  burger.addEventListener('click', function () {
    var open = menu.classList.toggle('open');
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  $$('#menu a').forEach(function (a) { a.addEventListener('click', closeMenu); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });
  toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });

  var sky = $('.sky'), sun = $('#sun'), arc = $('#arc'), arcLen = arc ? arc.getTotalLength() : 0;
  /* Récit au défilement façon Apple : la section reste fixe, les 3 moments se succèdent */
  var story = $('#ecole'), panes = $$('#ecole .pane'), sdots = $$('#ecole .story-dots li'), storyP = 0;
  var wide = window.matchMedia('(min-width: 861px) and (min-height: 600px)');
  function setupStory() { if (story) story.classList.toggle('is-story', wide.matches && !reduce); }
  setupStory();
  if (wide.addEventListener) wide.addEventListener('change', setupStory);
  function runStory() {
    if (!story) return;
    if (story.classList.contains('is-story')) {
      var r = story.getBoundingClientRect(), span = r.height - window.innerHeight;
      storyP = Math.min(1, Math.max(0, -r.top / (span || 1)));
    } else {
      var rs = story.getBoundingClientRect();
      storyP = Math.min(1, Math.max(0, (window.innerHeight - rs.top) / (rs.height + window.innerHeight)));
    }
    var phase = storyP < 0.36 ? 1 : storyP < 0.7 ? 2 : 3;
    if (story.getAttribute('data-phase') !== String(phase)) {
      story.setAttribute('data-phase', phase);
      panes.forEach(function (pn, i) { pn.classList.toggle('on', i === phase - 1); pn.classList.toggle('past', i < phase - 1); });
      sdots.forEach(function (d, i) { d.classList.toggle('on', i === phase - 1); });
    }
  }
  function placeSun() {
    if (!sky || !sun) return;
    var p = Math.min(1, storyP * 1.35);
    var r = sky.getBoundingClientRect();
    var pt = arc.getPointAtLength(arcLen * (reduce ? 0.5 : p));
    var sx = r.width / 1000, sy = r.height / 170;
    sun.style.transform = 'translate(' + (pt.x * sx) + 'px,' + (pt.y * sy) + 'px)';
  }
  var ticking = false;
  function onScroll() {
    if (ticking) return; ticking = true;
    requestAnimationFrame(function () {
      var y = window.scrollY, h = document.documentElement.scrollHeight - window.innerHeight;
      top.classList.toggle('scrolled', y > 10);
      toTop.classList.toggle('show', y > 900);
      if (bar) bar.style.transform = 'scaleX(' + (h > 0 ? y / h : 0) + ')';
      runStory();
      placeSun();
      ticking = false;
    });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  onScroll();

  /* Lien de menu actif selon la section visible */
  if ('IntersectionObserver' in window) {
    var links = $$('nav.links a[href^="#"]');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        links.forEach(function (a) { a.classList.toggle('active', a.getAttribute('href') === '#' + en.target.id); });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    links.forEach(function (a) { var t = $(a.getAttribute('href')); if (t) io.observe(t); });

    /* Compteurs animés */
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        cio.unobserve(en.target);
        var el = en.target, end = +el.getAttribute('data-count'), t0 = null;
        if (reduce) return;
        function step(ts) {
          t0 = t0 || ts;
          var k = Math.min(1, (ts - t0) / 1200);
          el.textContent = Math.round(end * (1 - Math.pow(1 - k, 3)));
          if (k < 1) requestAnimationFrame(step);
        }
        el.textContent = '0';
        requestAnimationFrame(step);
      });
    }, { threshold: 0.6 });
    $$('[data-count]').forEach(function (el) { cio.observe(el); });
  }

  /* ---------- Titre qui apparaît lettre par lettre ---------- */
  $$('[data-blur]').forEach(function (el) {
    if (reduce) return;
    var txt = el.textContent;
    el.setAttribute('aria-label', txt);
    var n = 0;
    el.innerHTML = txt.split(' ').map(function (w) {
      return '<span class="wd" aria-hidden="true">' + w.split('').map(function (c) {
        return '<span class="ch" style="--i:' + (n++) + '">' + c.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</span>';
      }).join('') + '</span>';
    }).join(' ');
  });

  /* ---------- Mots qui tournent ---------- */
  $$('.rot-w').forEach(function (box) {
    var k = 0;
    function words() { return (box.getAttribute('data-words-' + cur) || '').split('|'); }
    function swap() {
      var w = words(); k = (k + 1) % w.length;
      var old = box.firstElementChild, nw = document.createElement('span');
      nw.textContent = w[k];
      if (old) { old.classList.add('out'); setTimeout(function () { if (old.parentNode) old.remove(); }, 450); }
      nw.style.gridArea = '1/1'; if (old) old.style.gridArea = '1/1';
      box.appendChild(nw);
    }
    box._reset = function () { k = -1; swap(); };
    if (!reduce) setInterval(function () { if (!document.hidden) swap(); }, 2200);
  });

  /* ---------- Cartes inclinables et bouton aimanté (souris uniquement) ---------- */
  var finePointer = window.matchMedia && matchMedia('(hover: hover) and (pointer: fine)').matches;
  if (finePointer && !reduce) {
    $$('[data-tilt]').forEach(function (el) {
      el.addEventListener('pointermove', function (e) {
        var r = el.getBoundingClientRect(), x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
        el.classList.add('tilting');
        el.style.transform = 'perspective(900px) rotateX(' + ((0.5 - y) * 8).toFixed(2) + 'deg) rotateY(' + ((x - 0.5) * 10).toFixed(2) + 'deg) translateZ(0)';
        el.style.setProperty('--gx', (x * 100) + '%'); el.style.setProperty('--gy', (y * 100) + '%');
      });
      el.addEventListener('pointerleave', function () {
        el.classList.remove('tilting');
        el.style.transition = 'transform .6s cubic-bezier(.2,.8,.2,1)'; el.style.transform = '';
        setTimeout(function () { el.style.transition = ''; }, 600);
      });
    });
    $$('[data-magnet]').forEach(function (el) {
      var zone = 60;
      window.addEventListener('pointermove', function (e) {
        var r = el.getBoundingClientRect(), cx = r.left + r.width / 2, cy = r.top + r.height / 2;
        var dx = e.clientX - cx, dy = e.clientY - cy;
        if (Math.abs(dx) < r.width / 2 + zone && Math.abs(dy) < r.height / 2 + zone) {
          el.style.transform = 'translate(' + (dx * 0.18).toFixed(1) + 'px,' + (dy * 0.25).toFixed(1) + 'px)';
        } else if (el.style.transform) { el.style.transform = ''; }
      }, { passive: true });
    });
  }

  /* ---------- Liste des pièces à fournir (mémorisée sur l'appareil) ---------- */
  var boxes = $$('.check input[data-k]'), cnt = $('#docsCount');
  function countDocs() {
    var std = $$('[data-list="std"] input'), n = std.filter(function (b) { return b.checked; }).length;
    if (cnt) { cnt.textContent = n + '/' + std.length; cnt.classList.toggle('full', n === std.length); }
  }
  boxes.forEach(function (b) {
    b.checked = store.get('ps-doc-' + b.getAttribute('data-k')) === '1';
    b.addEventListener('change', function () { store.set('ps-doc-' + b.getAttribute('data-k'), b.checked ? '1' : '0'); countDocs(); });
  });
  countDocs();

  /* ---------- Galerie : visionneuse ---------- */
  var lb = $('#lightbox'), lbImg = $('#lbImg'), lbCap = $('#lbCap'), items = $$('#gallery .zoom'), idx = 0, lastFocus = null;
  function show(i) {
    idx = (i + items.length) % items.length;
    var it = items[idx];
    lbImg.src = it.getAttribute('data-src');
    lbImg.alt = it.getAttribute('data-cap-' + cur) || '';
    lbCap.textContent = (it.getAttribute('data-cap-' + cur) || '') + '  ' + (idx + 1) + ' / ' + items.length;
    lbImg.style.animation = 'none'; void lbImg.offsetWidth; lbImg.style.animation = '';
  }
  function openLb(i) { lastFocus = document.activeElement; lb.hidden = false; document.body.style.overflow = 'hidden'; show(i); $('.lb-close').focus(); }
  function closeLb() { lb.hidden = true; document.body.style.overflow = ''; if (lastFocus) lastFocus.focus(); }
  items.forEach(function (b, i) { b.addEventListener('click', function () { openLb(i); }); });
  if (lb) {
    $('.lb-close').addEventListener('click', closeLb);
    $('.lb-prev').addEventListener('click', function () { show(idx - 1); });
    $('.lb-next').addEventListener('click', function () { show(idx + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });
    document.addEventListener('keydown', function (e) {
      if (lb.hidden) return;
      if (e.key === 'Escape') closeLb();
      if (e.key === 'ArrowLeft') show(idx - 1);
      if (e.key === 'ArrowRight') show(idx + 1);
    });
    var x0 = null;
    lb.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 50) show(idx + (dx < 0 ? 1 : -1));
      x0 = null;
    });
  }

  /* ---------- Carte : chargée seulement à la demande ---------- */
  var mapBtn = $('#mapBtn');
  if (mapBtn) mapBtn.addEventListener('click', function () {
    var box = $('#mapFrame'), f = document.createElement('iframe');
    f.src = box.getAttribute('data-src');
    f.title = cur === 'en' ? 'Map: Potters School, Ouaga 2000' : 'Carte : Potters School, Ouaga 2000';
    f.loading = 'lazy'; f.referrerPolicy = 'no-referrer-when-downgrade';
    box.innerHTML = ''; box.appendChild(f); box.classList.add('loaded');
  });

  /* ---------- Formulaire en 3 étapes ---------- */
  var form = $('#form');
  if (!form) return;
  var steps = $$('.step', form), dots = $$('.stepper span', form), lines = $$('.stepper i', form), errKey = '';
  var v = function (id) { return $('#' + id).value.trim(); };
  function showErr(k) { errKey = k; $('#err').textContent = k ? T[cur][k] : ''; }
  function go(n) {
    steps.forEach(function (s) { s.classList.toggle('on', +s.getAttribute('data-step') === n); });
    dots.forEach(function (d, i) { d.classList.toggle('on', i === n - 1); d.classList.toggle('done', i < n - 1); });
    lines.forEach(function (l, i) { l.classList.toggle('done', i < n - 1); });
    showErr('');
    if (n === 3) buildRecap();
    var first = $('.step.on input, .step.on select, .step.on button', form);
    if (first && n > 1) first.focus({ preventScroll: true });
    var r = form.getBoundingClientRect();
    if (r.top < 60) window.scrollBy({ top: r.top - 90, behavior: reduce ? 'auto' : 'smooth' });
  }
  function mark(id, bad) { var el = $('#' + id); el.classList.toggle('bad', bad); if (bad) setTimeout(function () { el.classList.remove('bad'); }, 1500); return bad; }
  function valid(n) {
    var ok = true;
    if (n === 1) { if (mark('enfant', !v('enfant'))) ok = false; if (mark('classe', !v('classe'))) ok = false; }
    if (n === 2) {
      if (mark('parent', !v('parent'))) ok = false;
      var telOk = /^[\d\s+().-]{8,20}$/.test(v('tel'));
      if (mark('tel', !telOk)) { showErr(v('parent') ? 'tel' : 'err'); return false; }
    }
    if (!ok) showErr('err');
    return ok;
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function clsLabel() { var s = $('#classe'); return s.options[s.selectedIndex] ? s.options[s.selectedIndex].textContent : ''; }
  function buildRecap() {
    var r = T[cur].recap, rows = [[r[0], v('enfant')], [r[1], v('age') || '-'], [r[2], clsLabel()], [r[3], v('parent')], [r[4], v('tel')]];
    if (v('msg')) rows.push([r[5], v('msg')]);
    $('#recap').innerHTML = rows.map(function (x) { return '<dt>' + esc(x[0]) + '</dt><dd>' + esc(x[1]) + '</dd>'; }).join('');
  }
  $$('.next', form).forEach(function (b) {
    b.addEventListener('click', function () { var n = +b.closest('.step').getAttribute('data-step'); if (valid(n)) go(n + 1); });
  });
  $$('.prev', form).forEach(function (b) {
    b.addEventListener('click', function () { go(+b.closest('.step').getAttribute('data-step') - 1); });
  });
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
      e.preventDefault();
      var n = +e.target.closest('.step').getAttribute('data-step');
      if (n < 3 && valid(n)) go(n + 1);
    }
  });
  function waText() {
    var r = T[cur].recap;
    return T[cur].wa + '\n\n' + r[0] + ' : ' + v('enfant') + (v('age') ? ' (' + v('age') + ')' : '') + '\n' + r[2] + ' : ' + clsLabel() +
      '\n' + r[3] + ' : ' + v('parent') + '\n' + r[4] + ' : ' + v('tel') + (v('msg') ? '\n\n' + v('msg') : '');
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!valid(1)) { go(1); return; }
    if (!valid(2)) { go(2); return; }
    var send = $('#send');
    if (document.body.getAttribute('data-static') === '1') {
      /* Version sans serveur : la demande part directement sur WhatsApp */
      var w = document.body.getAttribute('data-wa');
      if (w) window.open('https://wa.me/' + w + '?text=' + encodeURIComponent(waText()), '_blank', 'noopener');
      steps.forEach(function (s) { s.classList.remove('on'); });
      $('.stepper', form).hidden = true;
      var dd = $('#done'); dd.hidden = false;
      var bb = $('#waBtn'); if (bb && w) bb.href = 'https://wa.me/' + w + '?text=' + encodeURIComponent(waText());
      return;
    }
    send.disabled = true;
    var fd = new FormData(form); fd.append('lang', cur);
    var wa = document.body.getAttribute('data-wa'), waUrl = wa ? 'https://wa.me/' + wa + '?text=' + encodeURIComponent(waText()) : '';
    fetch('api/preinscription.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
      .then(function (res) {
        send.disabled = false;
        if (res.j && res.j.ok) {
          steps.forEach(function (s) { s.classList.remove('on'); });
          $('.stepper', form).hidden = true;
          var d = $('#done'); d.hidden = false; d.focus({ preventScroll: true });
          var b = $('#waBtn'); if (b && waUrl) b.href = waUrl;
          showErr('');
        } else {
          showErr(res.s === 429 ? 'errLimit' : (res.j && res.j.error === 'token' ? 'errTok' : 'errSend'));
        }
      })
      .catch(function () {
        send.disabled = false;
        if (waUrl) window.open(waUrl, '_blank', 'noopener'); else showErr('errSend');
      });
  });
})();
