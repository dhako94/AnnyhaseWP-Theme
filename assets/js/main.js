/* Annyhase Theme – main.js */
(function () {
  'use strict';

  /* -----------------------------------------------
     Sticky Header: add "scrolled" class on scroll
  ----------------------------------------------- */
  const header = document.getElementById('site-header');
  if (header) {
    const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 30);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* -----------------------------------------------
     Mobile Nav Toggle
  ----------------------------------------------- */
  const toggle = document.getElementById('nav-toggle');
  const navMenu = document.getElementById('nav-menu');
  if (toggle && navMenu) {
    toggle.addEventListener('click', () => {
      const open = navMenu.classList.toggle('open');
      toggle.classList.toggle('active', open);
      toggle.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', (e) => {
      if (!toggle.contains(e.target) && !navMenu.contains(e.target)) {
        navMenu.classList.remove('open');
        toggle.classList.remove('active');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
    /* Close menu when any nav link is clicked (covers regular page links, not just hash) */
    navMenu.addEventListener('click', (e) => {
      if (e.target.closest('a')) {
        navMenu.classList.remove('open');
        toggle.classList.remove('active');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* -----------------------------------------------
     Scroll Reveal
  ----------------------------------------------- */
  const revealEls = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && revealEls.length) {
    const observer = new IntersectionObserver(
      (entries) => entries.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); } }),
      { threshold: 0.05, rootMargin: '0px 0px -20px 0px' }
    );
    revealEls.forEach((el) => observer.observe(el));
  } else {
    revealEls.forEach((el) => el.classList.add('visible'));
  }

  /* -----------------------------------------------
     Smooth Scroll – works for #hash, /#hash, and absolute URLs
  ----------------------------------------------- */
  document.querySelectorAll('a[href*="#"]').forEach((link) => {
    link.addEventListener('click', (e) => {
      let href = link.getAttribute('href');
      /* Absolute URL on same domain → extract hash only */
      try {
        const url = new URL(href, location.href);
        if (url.origin !== location.origin) return;
        if (url.pathname !== '/' && url.pathname !== location.pathname) return;
        href = url.hash;
      } catch (_) { return; }
      if (!href || href === '#') return;
      const target = document.querySelector(href);
      if (!target) return;
      e.preventDefault();
      /* Close mobile menu */
      if (navMenu) { navMenu.classList.remove('open'); }
      if (toggle)  { toggle.classList.remove('active'); toggle.setAttribute('aria-expanded', 'false'); }
      const offset = header ? header.offsetHeight + 16 : 80;
      window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
    });
  });

  /* -----------------------------------------------
     Contact Form AJAX
  ----------------------------------------------- */
  const kfForm = document.getElementById('kf-form');
  if (kfForm) {
    kfForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const submitBtn  = document.getElementById('kf-submit');
      const submitText = document.getElementById('kf-submit-text');
      const loader     = document.getElementById('kf-submit-loader');
      const arrow      = document.getElementById('kf-arrow');
      const successBox = document.getElementById('kf-success');
      const errorBox   = document.getElementById('kf-error');
      const errorMsg   = document.getElementById('kf-error-msg');

      if (!kfForm.checkValidity()) { kfForm.reportValidity(); return; }

      submitBtn.disabled = true;
      submitText.hidden  = true;
      if (arrow)  arrow.hidden  = true;
      loader.hidden      = false;
      errorBox.hidden    = true;

      /* reCAPTCHA v3 token – only when site key is configured */
      let recaptchaToken = '';
      if (annyhaseData.recaptchaSiteKey && typeof grecaptcha !== 'undefined') {
        try {
          recaptchaToken = await grecaptcha.execute(annyhaseData.recaptchaSiteKey, { action: 'contact' });
        } catch (_) { /* reCAPTCHA unavailable – send anyway */ }
      }

      const subjectEl = kfForm.querySelector('[name="subject"]:checked');
      const data = new FormData();
      data.append('action',          'annyhase_contact');
      data.append('nonce',           annyhaseData.nonce);
      data.append('name',            kfForm.querySelector('[name="name"]').value.trim());
      data.append('email',           kfForm.querySelector('[name="email"]').value.trim());
      data.append('subject',         subjectEl ? subjectEl.value : '');
      data.append('message',         kfForm.querySelector('[name="message"]').value.trim());
      data.append('privacy',         kfForm.querySelector('[name="privacy"]').checked ? '1' : '');
      data.append('website',         kfForm.querySelector('[name="website"]')?.value || '');
      data.append('recaptcha_token', recaptchaToken);

      try {
        const res  = await fetch(annyhaseData.ajaxUrl, { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
          const successText = document.getElementById('kf-success-text');
          if (successText && json.data?.message) successText.textContent = json.data.message;
          kfForm.hidden    = true;
          successBox.hidden = false;
          successBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
          errorMsg.textContent = json.data?.message || 'Fehler beim Senden.';
          errorBox.hidden = false;
          errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      } catch {
        errorMsg.textContent = 'Netzwerkfehler. Bitte versuche es erneut.';
        errorBox.hidden = false;
      } finally {
        submitBtn.disabled = false;
        submitText.hidden  = false;
        if (arrow)  arrow.hidden  = false;
        loader.hidden      = true;
      }
    });
  }

  /* -----------------------------------------------
     Scroll-Spy: highlight active anchor in nav
  ----------------------------------------------- */
  const navLinks = document.querySelectorAll('.nav-menu a[href*="#"]');
  if (navLinks.length) {
    const sections  = [];
    const homeLink  = document.querySelector(
      '.nav-menu a[href="' + location.origin + '/"], .nav-menu a[href="/"]'
    );

    navLinks.forEach((link) => {
      try {
        const url = new URL(link.getAttribute('href'), location.href);
        if (url.origin !== location.origin) return;
        if (url.hash && (url.pathname === '/' || url.pathname === location.pathname)) {
          const el = document.querySelector(url.hash);
          if (el) sections.push({ el, link });
        }
      } catch (_) {}
    });

    if (sections.length) {
      const setActive = () => {
        const offset = (header ? header.offsetHeight : 80) + 32;
        let active = null;
        sections.forEach(({ el, link }) => {
          if (el.getBoundingClientRect().top <= offset) active = link;
        });
        sections.forEach(({ link }) => link.classList.toggle('is-active', link === active));
        /* Home link active only when no section is in view */
        if (homeLink) homeLink.classList.toggle('is-active', active === null);
      };
      window.addEventListener('scroll', setActive, { passive: true });
      setActive();
    }
  }


  /* -----------------------------------------------
     Product Description Toggle
  ----------------------------------------------- */
  var pgDesc   = document.getElementById('pg-desc');
  var pgToggle = document.getElementById('pg-desc-toggle');
  var pgFade   = document.getElementById('pg-desc-fade');
  if (pgDesc && pgToggle) {
    if (pgDesc.scrollHeight <= pgDesc.offsetHeight + 4) {
      pgToggle.style.display = 'none';
      if (pgFade) pgFade.style.display = 'none';
    } else {
      pgToggle.addEventListener('click', function () {
        var open = pgDesc.classList.toggle('is-open');
        pgDesc.style.maxHeight = open ? pgDesc.scrollHeight + 'px' : '';
        pgToggle.textContent   = open
          ? (annyhaseData.i18n ? annyhaseData.i18n.readLess : 'Weniger anzeigen')
          : (annyhaseData.i18n ? annyhaseData.i18n.readMore : 'Mehr lesen');
      });
    }
  }

  /* -----------------------------------------------
     Related Products Slider
  ----------------------------------------------- */
  var rsTrack = document.getElementById('rs-track');
  var rsPrev  = document.getElementById('rs-prev');
  var rsNext  = document.getElementById('rs-next');
  if (rsTrack && rsPrev && rsNext) {
    function rsScrollAmt() {
      var card = rsTrack.querySelector('.related-card');
      if (!card) return 220;
      var gap = parseInt(getComputedStyle(rsTrack).gap) || 16;
      return card.offsetWidth + gap;
    }
    rsPrev.addEventListener('click', function () { rsTrack.scrollBy({ left: -rsScrollAmt(), behavior: 'smooth' }); });
    rsNext.addEventListener('click', function () { rsTrack.scrollBy({ left:  rsScrollAmt(), behavior: 'smooth' }); });
    function rsSyncBtns() {
      rsPrev.disabled = rsTrack.scrollLeft < 4;
      rsNext.disabled = rsTrack.scrollLeft >= rsTrack.scrollWidth - rsTrack.clientWidth - 4;
    }
    rsTrack.addEventListener('scroll', rsSyncBtns, { passive: true });
    rsSyncBtns();
  }

  /* -----------------------------------------------
     Instagram Gallery Lightbox (front page)
  ----------------------------------------------- */
  (function () {
    var items     = Array.from(document.querySelectorAll('.gallery-item[data-lb-src]'));
    var lb        = document.getElementById('gallery-lb');
    if (!items.length || !lb) return;

    var lbImg     = document.getElementById('lb-img');
    var lbCaption = document.getElementById('lb-caption');
    var lbClose   = document.getElementById('lb-close');
    var lbPrev    = document.getElementById('lb-prev');
    var lbNext    = document.getElementById('lb-next');
    var current   = 0;

    function show(index) {
      current = (index + items.length) % items.length;
      var item = items[current];
      lbImg.src             = item.dataset.lbSrc;
      lbImg.alt             = item.dataset.lbCaption || '';
      lbCaption.textContent = item.dataset.lbCaption || '';
      lbPrev.style.display  = items.length > 1 ? '' : 'none';
      lbNext.style.display  = items.length > 1 ? '' : 'none';
    }
    function open(index) {
      show(index);
      lb.classList.add('is-open');
      document.body.style.overflow = 'hidden';
      lbClose.focus();
    }
    function close() {
      lb.classList.remove('is-open');
      document.body.style.overflow = '';
    }

    items.forEach(function (item, i) {
      item.addEventListener('click', function () { open(i); });
      item.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(i); } });
    });

    lbClose.addEventListener('click', close);
    lbPrev.addEventListener('click', function () { show(current - 1); });
    lbNext.addEventListener('click', function () { show(current + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) close(); });

    document.addEventListener('keydown', function (e) {
      if (!lb.classList.contains('is-open')) return;
      if (e.key === 'Escape')     close();
      if (e.key === 'ArrowLeft')  show(current - 1);
      if (e.key === 'ArrowRight') show(current + 1);
    });

    var startX = 0;
    lb.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 40) show(dx < 0 ? current + 1 : current - 1);
    });
  })();

  /* -----------------------------------------------
     Product Gallery + Lightbox (single product page)
  ----------------------------------------------- */
  (function () {
    if (typeof annyhaseGallery === 'undefined') return;

    var allItems   = annyhaseGallery;
    var cur        = 0;
    var lb         = document.getElementById('lightbox');
    var lbImg      = document.getElementById('lb-img');
    var lbVideo    = document.getElementById('lb-video');
    var lbVideoSrc = document.getElementById('lb-video-src');
    var lbCtr      = document.getElementById('lb-counter');
    var mainEl     = document.getElementById('pg-main');
    var mainImg    = document.getElementById('pg-main-img');
    var mainVideo  = document.getElementById('pg-main-video');
    var videoEl    = document.getElementById('pg-video-player');
    var thumbsEl   = document.getElementById('pg-thumbs');

    if (!lb || !mainEl) return;

    /* Thumbnail click → switch main image */
    if (thumbsEl) {
      thumbsEl.querySelectorAll('.product-gallery__thumb').forEach(function (th) {
        th.addEventListener('click', function () {
          switchMain(parseInt(this.dataset.index) || 0);
        });
      });
    }

    /* Thumbnail strip prev/next buttons */
    var thumbsPrev = document.getElementById('pg-thumbs-prev');
    var thumbsNext = document.getElementById('pg-thumbs-next');

    function thumbScrollAmt() {
      if (!thumbsEl) return 96;
      var btn = thumbsEl.querySelector('.product-gallery__thumb');
      if (!btn) return 96;
      return btn.offsetWidth + (parseInt(getComputedStyle(thumbsEl).gap) || 6);
    }
    function centerThumb(th) {
      if (!thumbsEl || !th) return;
      var stripRect  = thumbsEl.getBoundingClientRect();
      var thRect     = th.getBoundingClientRect();
      var currentPos = thumbsEl.scrollLeft + (thRect.left - stripRect.left);
      var target     = currentPos - (stripRect.width / 2) + (thRect.width / 2);
      thumbsEl.scrollTo({ left: Math.max(0, target), behavior: 'smooth' });
    }
    /* Start hidden; syncThumbNav reveals them only when strip overflows */
    if (thumbsPrev) thumbsPrev.style.display = 'none';
    if (thumbsNext) thumbsNext.style.display = 'none';
    function syncThumbNav() {
      if (!thumbsEl) return;
      var overflow = thumbsEl.scrollWidth > thumbsEl.clientWidth + 4;
      if (thumbsPrev) thumbsPrev.style.display = overflow ? '' : 'none';
      if (thumbsNext) thumbsNext.style.display = overflow ? '' : 'none';
    }
    if (thumbsPrev) thumbsPrev.addEventListener('click', function () { thumbsEl.scrollBy({ left: -thumbScrollAmt(), behavior: 'smooth' }); });
    if (thumbsNext) thumbsNext.addEventListener('click', function () { thumbsEl.scrollBy({ left:  thumbScrollAmt(), behavior: 'smooth' }); });
    if (thumbsEl) {
      thumbsEl.addEventListener('scroll', syncThumbNav, { passive: true });
      requestAnimationFrame(syncThumbNav);
      window.addEventListener('resize', syncThumbNav, { passive: true });
    }

    function switchMain(idx) {
      var item = allItems[idx];
      if (!item) return;
      if (item.type === 'video') {
        if (mainImg)   { mainImg.style.display = 'none'; }
        if (mainVideo) { mainVideo.style.display = 'block'; }
        if (videoEl)   { videoEl.play(); }
      } else {
        if (mainVideo) { mainVideo.style.display = 'none'; if (videoEl) videoEl.pause(); }
        if (mainImg) {
          mainImg.style.display   = '';
          mainImg.style.opacity   = '0';
          mainImg.style.transform = 'scale(.97)';
          setTimeout(function () {
            mainImg.src = item.large;
            mainImg.alt = item.alt || '';
            mainImg.onload = function () {
              mainImg.style.opacity   = '1';
              mainImg.style.transform = 'scale(1)';
            };
            setTimeout(function () {
              mainImg.style.opacity   = '1';
              mainImg.style.transform = 'scale(1)';
            }, 350);
          }, 160);
        }
      }
      mainEl.dataset.index = idx;
      if (thumbsEl) {
        thumbsEl.querySelectorAll('.product-gallery__thumb').forEach(function (t) {
          t.classList.toggle('is-active', parseInt(t.dataset.index) === idx);
        });
        var activeTh = thumbsEl.querySelector('.product-gallery__thumb.is-active');
        centerThumb(activeTh);
        syncThumbNav();
      }
    }

    /* Main image click → lightbox (video plays inline instead) */
    mainEl.addEventListener('click', function () {
      var idx  = parseInt(this.dataset.index) || 0;
      var item = allItems[idx];
      if (!item || item.type === 'video') return;
      openLightbox(idx);
    });

    /* Lightbox */
    function showLbItem(idx) {
      var item = allItems[idx];
      if (!item) return;
      resetZoom();
      if (item.type === 'video') {
        lbImg.style.display = 'none';
        if (lbVideo && lbVideoSrc) {
          lbVideoSrc.src = item.url;
          lbVideo.load();
          lbVideo.style.display = '';
          lbVideo.play().catch(function () {});
        }
      } else {
        if (lbVideo) { lbVideo.pause(); lbVideo.style.display = 'none'; }
        lbImg.style.display = '';
        lbImg.src = item.full || item.large;
        lbImg.alt = item.alt || '';
      }
      lbCtr.textContent = allItems.length > 1 ? (idx + 1) + ' / ' + allItems.length : '';
    }
    function openLightbox(idx) {
      cur = idx;
      showLbItem(cur);
      lb.classList.add('open');
      document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
      resetZoom();
      if (lbVideo) { lbVideo.pause(); lbVideo.style.display = 'none'; }
      lb.classList.remove('open');
      document.body.style.overflow = '';
    }
    function lbGo(n) {
      cur = (n + allItems.length) % allItems.length;
      showLbItem(cur);
    }

    document.getElementById('lb-close').addEventListener('click', closeLightbox);
    document.getElementById('lb-prev').addEventListener('click', function (e) { e.stopPropagation(); lbGo(cur - 1); });
    document.getElementById('lb-next').addEventListener('click', function (e) { e.stopPropagation(); lbGo(cur + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) closeLightbox(); });

    document.addEventListener('keydown', function (e) {
      if (!lb.classList.contains('open')) return;
      if (e.key === 'Escape')     closeLightbox();
      if (e.key === 'ArrowLeft')  lbGo(cur - 1);
      if (e.key === 'ArrowRight') lbGo(cur + 1);
    });

    if (allItems.length <= 1) {
      var lbp = document.getElementById('lb-prev');
      var lbn = document.getElementById('lb-next');
      if (lbp) lbp.style.display = 'none';
      if (lbn) lbn.style.display = 'none';
    }

    /* Zoom (image only, not video) */
    var zoomScale = 1, panX = 0, panY = 0;
    var zoomOriginX = 50, zoomOriginY = 50;
    var isDragging = false, wasDragging = false;
    var dragSX = 0, dragSY = 0, panSX = 0, panSY = 0;

    function applyZoom() {
      lbImg.style.transformOrigin = zoomOriginX + '% ' + zoomOriginY + '%';
      lbImg.style.transform = 'translate(' + panX + 'px,' + panY + 'px) scale(' + zoomScale + ')';
      lbImg.style.cursor = zoomScale > 1 ? 'grab' : 'zoom-in';
    }
    function resetZoom() {
      zoomScale = 1; panX = 0; panY = 0;
      zoomOriginX = 50; zoomOriginY = 50;
      lbImg.style.transform = '';
      lbImg.style.transformOrigin = '';
      lbImg.style.cursor = 'zoom-in';
    }

    lbImg.addEventListener('click', function (e) {
      e.stopPropagation();
      if (wasDragging) { wasDragging = false; return; }
      if (zoomScale > 1) { resetZoom(); return; }
      var rect = lbImg.getBoundingClientRect();
      zoomOriginX = ((e.clientX - rect.left) / rect.width)  * 100;
      zoomOriginY = ((e.clientY - rect.top)  / rect.height) * 100;
      zoomScale = 2.5; panX = 0; panY = 0;
      applyZoom();
    });

    lbImg.addEventListener('wheel', function (e) {
      e.preventDefault();
      var rect = lbImg.getBoundingClientRect();
      var newScale = Math.min(5, Math.max(1, zoomScale + (e.deltaY > 0 ? -0.35 : 0.35)));
      if (newScale <= 1) { resetZoom(); return; }
      zoomOriginX = ((e.clientX - rect.left) / rect.width)  * 100;
      zoomOriginY = ((e.clientY - rect.top)  / rect.height) * 100;
      zoomScale = newScale;
      applyZoom();
    }, { passive: false });

    lbImg.addEventListener('mousedown', function (e) {
      if (zoomScale <= 1) return;
      e.preventDefault();
      isDragging = true; wasDragging = false;
      dragSX = e.clientX; dragSY = e.clientY;
      panSX = panX; panSY = panY;
      lbImg.style.cursor = 'grabbing';
    });
    document.addEventListener('mousemove', function (e) {
      if (!isDragging) return;
      if (Math.abs(e.clientX - dragSX) > 3 || Math.abs(e.clientY - dragSY) > 3) wasDragging = true;
      panX = panSX + (e.clientX - dragSX);
      panY = panSY + (e.clientY - dragSY);
      lbImg.style.transform = 'translate(' + panX + 'px,' + panY + 'px) scale(' + zoomScale + ')';
    });
    document.addEventListener('mouseup', function () {
      if (!isDragging) return;
      isDragging = false;
      lbImg.style.cursor = zoomScale > 1 ? 'grab' : 'zoom-in';
    });

    /* Pinch-to-zoom */
    var pinchDist0 = 0, zoomScale0 = 1;
    lb.addEventListener('touchstart', function (e) {
      if (e.touches.length !== 2) return;
      var dx = e.touches[0].clientX - e.touches[1].clientX;
      var dy = e.touches[0].clientY - e.touches[1].clientY;
      pinchDist0 = Math.sqrt(dx * dx + dy * dy);
      zoomScale0 = zoomScale;
    }, { passive: true });
    lb.addEventListener('touchmove', function (e) {
      if (e.touches.length !== 2) return;
      e.preventDefault();
      var dx = e.touches[0].clientX - e.touches[1].clientX;
      var dy = e.touches[0].clientY - e.touches[1].clientY;
      var newScale = Math.min(5, Math.max(1, zoomScale0 * (Math.sqrt(dx * dx + dy * dy) / pinchDist0)));
      if (newScale < 1.05) { resetZoom(); return; }
      zoomScale = newScale;
      applyZoom();
    }, { passive: false });

    /* Main image swipe + prev/next with loop */
    var pgNavPrev = document.getElementById('pg-nav-prev');
    var pgNavNext = document.getElementById('pg-nav-next');

    function getMainIdx() { return parseInt(mainEl.dataset.index) || 0; }
    function navBy(offset) {
      var next = (getMainIdx() + offset + allItems.length) % allItems.length;
      switchMain(next);
    }

    if (pgNavPrev) pgNavPrev.addEventListener('click', function (e) { e.stopPropagation(); navBy(-1); });
    if (pgNavNext) pgNavNext.addEventListener('click', function (e) { e.stopPropagation(); navBy(1); });

    if (allItems.length > 1) {
      var pgSwX = 0;
      mainEl.addEventListener('touchstart', function (e) { pgSwX = e.touches[0].clientX; }, { passive: true });
      mainEl.addEventListener('touchend', function (e) {
        var dx = e.changedTouches[0].clientX - pgSwX;
        if (Math.abs(dx) > 40) navBy(dx < 0 ? 1 : -1);
      });
    }
  })();

})();
