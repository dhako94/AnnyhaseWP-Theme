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
     Smooth Scroll – funktioniert für #hash UND /#hash UND absolute URLs
  ----------------------------------------------- */
  document.querySelectorAll('a[href*="#"]').forEach((link) => {
    link.addEventListener('click', (e) => {
      let href = link.getAttribute('href');
      /* Absolute URL auf gleicher Domain → nur den Hash-Teil nehmen */
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
      /* Mobiles Menü schließen */
      if (navMenu) { navMenu.classList.remove('open'); }
      if (toggle)  { toggle.classList.remove('active'); toggle.setAttribute('aria-expanded', 'false'); }
      const offset = header ? header.offsetHeight + 16 : 80;
      window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
    });
  });

  /* -----------------------------------------------
     Kontaktformular AJAX
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

      // reCAPTCHA v3 token – nur wenn Site Key konfiguriert
      let recaptchaToken = '';
      if (annyhaseData.recaptchaSiteKey && typeof grecaptcha !== 'undefined') {
        try {
          recaptchaToken = await grecaptcha.execute(annyhaseData.recaptchaSiteKey, { action: 'contact' });
        } catch (_) { /* reCAPTCHA nicht verfügbar – trotzdem senden */ }
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
     Scroll-Spy: Anchor-Links im Nav aktiv markieren
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
        /* Start-Link nur aktiv wenn kein Abschnitt erreicht wurde */
        if (homeLink) homeLink.classList.toggle('is-active', active === null);
      };
      window.addEventListener('scroll', setActive, { passive: true });
      setActive();
    }
  }

})();
