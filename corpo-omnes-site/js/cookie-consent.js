// Bannière de consentement cookies (RGPD)
// stocke le choix 6 mois, émet un event custom à chaque sauvegarde
(function () {
  'use strict';

  const COOKIE_NAME    = 'corpo_consent';
  const LS_KEY         = 'corpo_consent_v1';
  const SS_KEY         = 'corpo_consent_v1';
  const COOKIE_VERSION = 1;
  const COOKIE_MAX_AGE = 60 * 60 * 24 * 180; // 6 mois

  const root = document.querySelector('[data-cookie-root]');
  if (!root) return;

  const banner   = root.querySelector('[data-cc-banner]');
  const modal    = root.querySelector('[data-cc-modal]');
  const overlay  = root.querySelector('[data-cc-overlay]');
  const toggles  = root.querySelectorAll('input[data-cc-cat]');

  if (!banner || !modal || !overlay) {
    console.warn('[cookie-consent] Éléments manquants (bannière / modale).');
    return;
  }

  // path=/ obligatoire sinon le cookie disparaît hors du sous-dossier courant
  const COOKIE_PATH = '/';

  function setCookie(name, value, maxAge) {
    if (typeof navigator !== 'undefined' && navigator.cookieEnabled === false) return;
    const isSecure = location.protocol === 'https:';
    let cookie = `${name}=${encodeURIComponent(value)}; max-age=${maxAge}; path=${COOKIE_PATH}; SameSite=Lax`;
    if (isSecure) cookie += '; Secure';
    try {
      document.cookie = cookie;
    } catch (e) { /* quota ou erreur navigateur, on ignore */ }
  }

  function setLocalStorage(json) {
    try {
      localStorage.setItem(LS_KEY, json);
    } catch (e) { /* mode privé iOS bloque le localStorage */ }
  }

  function setSessionStorage(json) {
    try {
      sessionStorage.setItem(SS_KEY, json);
    } catch (e) { /* idem pour sessionStorage */ }
  }

  function getLocalStorageParsed() {
    try {
      const raw = localStorage.getItem(LS_KEY);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function getSessionStorageParsed() {
    try {
      const raw = sessionStorage.getItem(SS_KEY);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  // lecture cookie compatible Safari (séparateur ";" avec ou sans espace)
  function getCookie(name) {
    const parts = (document.cookie || '').split(';');
    for (let i = 0; i < parts.length; i++) {
      const part = parts[i].replace(/^\s+/, '');
      if (!part || part.indexOf(name + '=') !== 0) continue;
      const enc = part.slice(name.length + 1);
      try {
        return JSON.parse(decodeURIComponent(enc));
      } catch (e) {
        return null;
      }
    }
    return null;
  }

  function consentVersionOk(c) {
    return c && Number(c.v) === COOKIE_VERSION;
  }

  function readConsent() {
    let c = getCookie(COOKIE_NAME);
    if (!consentVersionOk(c)) {
      c = getLocalStorageParsed();
    }
    if (!consentVersionOk(c)) {
      c = getSessionStorageParsed();
    }
    if (!consentVersionOk(c)) return null;
    // Safari peut avoir effacé un des stockages, on re-synchronise les trois
    try {
      const json = JSON.stringify(c);
      setCookie(COOKIE_NAME, json, COOKIE_MAX_AGE);
      setLocalStorage(json);
      setSessionStorage(json);
    } catch (e2) { /* ignore */ }
    return c;
  }

  function writeConsent(prefs) {
    const payload = {
      v: COOKIE_VERSION,
      t: Math.floor(Date.now() / 1000),
      preferences: !!prefs.preferences,
      analytics:   !!prefs.analytics,
      marketing:   !!prefs.marketing,
    };
    const json = JSON.stringify(payload);
    setCookie(COOKIE_NAME, json, COOKIE_MAX_AGE);
    setLocalStorage(json);
    setSessionStorage(json);
    return payload;
  }

  function applyTogglesFrom(consent) {
    toggles.forEach(t => {
      const cat = t.getAttribute('data-cc-cat');
      t.checked = consent ? !!consent[cat] : false;
    });
  }

  function dispatchChange(consent) {
    document.dispatchEvent(new CustomEvent('corpo:cookies-changed', { detail: consent }));
    window.corpoCookies = Object.assign({}, consent);
  }

  function showRoot() {
    root.hidden = false;
    root.removeAttribute('hidden');
  }
  function showBanner(){ banner.classList.add('is-open'); }
  function hideBanner(){ banner.classList.remove('is-open'); }
  function openModal() {
    showRoot();
    overlay.hidden = false;
    modal.hidden   = false;
    document.body.classList.add('cc-no-scroll');
    requestAnimationFrame(() => {
      overlay.classList.add('is-open');
      modal.classList.add('is-open');
    });
    const firstFocus = modal.querySelector('.cc-modal__close');
    if (firstFocus) firstFocus.focus();
  }
  function closeModal() {
    overlay.classList.remove('is-open');
    modal.classList.remove('is-open');
    document.body.classList.remove('cc-no-scroll');
    setTimeout(() => {
      overlay.hidden = true;
      modal.hidden   = true;
    }, 220);
  }

  function acceptAll() {
    const consent = writeConsent({ preferences: true, analytics: true, marketing: true });
    applyTogglesFrom(consent);
    hideBanner();
    closeModal();
    dispatchChange(consent);
  }

  function refuseAll() {
    const consent = writeConsent({ preferences: false, analytics: false, marketing: false });
    applyTogglesFrom(consent);
    hideBanner();
    closeModal();
    dispatchChange(consent);
  }

  function saveCurrent() {
    const prefs = {};
    toggles.forEach(t => { prefs[t.getAttribute('data-cc-cat')] = t.checked; });
    const consent = writeConsent(prefs);
    hideBanner();
    closeModal();
    dispatchChange(consent);
  }

  // ─── Délégation d'événements ────────────────────────────────
  root.addEventListener('click', function (e) {
    const target = e.target.closest('[data-cc-action]');
    if (!target) return;
    const action = target.getAttribute('data-cc-action');
    if (action === 'accept') return acceptAll();
    if (action === 'refuse') return refuseAll();
    if (action === 'open')   return openModal();
    if (action === 'save')   return saveCurrent();
    if (action === 'close')  return closeModal();
  });

  overlay.addEventListener('click', closeModal);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
  });

  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-cookie-pref]');
    if (!trigger) return;
    e.preventDefault();
    const consent = readConsent() || {};
    applyTogglesFrom(consent);
    openModal();
  });

  try {
    const existing = readConsent();
    if (existing) {
      applyTogglesFrom(existing);
      dispatchChange(existing);
      // déjà consenti → pas de bannière
    } else {
      applyTogglesFrom(null);
      showRoot();
      // double rAF nécessaire sur certains mobiles pour que la transition s'applique
      requestAnimationFrame(function () {
        requestAnimationFrame(showBanner);
      });
      dispatchChange({ preferences: false, analytics: false, marketing: false });
    }
  } catch (err) {
    console.error('[cookie-consent]', err);
    showRoot();
    requestAnimationFrame(function () {
      requestAnimationFrame(showBanner);
    });
  }
})();
