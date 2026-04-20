/**
 * params_capture.js
 *
 * Captura TODOS os parâmetros de tracking no primeiro acesso do usuário e persiste
 * em localStorage + cookie (fallback) por 30 dias. Usa first-touch attribution:
 * depois de salvos, os parâmetros da primeira visita nunca são sobrescritos por
 * visitas subsequentes — só é atualizada a URL da última página (last_url).
 *
 * Uso no código:
 *   const tp = window.getTrackingParams();
 *   // tp.utm_source, tp.ttclid, tp.fbclid, tp.referrer, tp.landing_url, etc.
 */
(function () {
  var STORAGE_KEY = 'esco_tracking_params';
  var COOKIE_KEY = 'esco_tp';
  var TTL_DAYS = 30;

  var CAPTURE_KEYS = [
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id',
    'src', 'sck',
    'ttclid', 'ttp',
    'fbclid',
    'gclid', 'gbraid', 'wbraid',
    'msclkid',
    'ref', 'source', 'campaign'
  ];

  function nowMs() { return Date.now(); }
  function ttlMs() { return TTL_DAYS * 24 * 60 * 60 * 1000; }

  function readStorage() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        var obj = JSON.parse(raw);
        if (obj && obj._expires && obj._expires > nowMs()) return obj;
      }
    } catch (_) {}
    try {
      var cookieMatch = document.cookie.match(new RegExp('(?:^|; )' + COOKIE_KEY + '=([^;]*)'));
      if (cookieMatch) {
        var decoded = decodeURIComponent(cookieMatch[1]);
        var obj2 = JSON.parse(decoded);
        if (obj2 && obj2._expires && obj2._expires > nowMs()) return obj2;
      }
    } catch (_) {}
    return null;
  }

  function writeStorage(obj) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(obj)); } catch (_) {}
    try {
      var val = encodeURIComponent(JSON.stringify(obj));
      var expires = new Date(nowMs() + ttlMs()).toUTCString();
      document.cookie = COOKIE_KEY + '=' + val + '; expires=' + expires + '; path=/; SameSite=Lax';
    } catch (_) {}
  }

  function readCookie(name) {
    try {
      var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
      return m ? decodeURIComponent(m[1]) : null;
    } catch (_) { return null; }
  }

  function extractFromUrl() {
    var out = {};
    try {
      var qs = new URLSearchParams(window.location.search);
      CAPTURE_KEYS.forEach(function (k) {
        var v = qs.get(k);
        if (v !== null && v !== '') out[k] = v;
      });
    } catch (_) {}
    return out;
  }

  function capture() {
    var existing = readStorage();
    var fromUrl = extractFromUrl();

    // Cookies do TikTok para EMQ/SKAN
    var cookieTtp = readCookie('_ttp');
    var cookieTtclid = readCookie('ttclid');
    if (cookieTtp && !fromUrl.ttp) fromUrl.ttp = cookieTtp;
    if (cookieTtclid && !fromUrl.ttclid) fromUrl.ttclid = cookieTtclid;

    var merged;
    if (existing) {
      // First-touch: mantém os parâmetros da primeira visita.
      // Só sobrescreve chaves ausentes (útil quando a primeira visita foi orgânica).
      merged = Object.assign({}, existing);
      Object.keys(fromUrl).forEach(function (k) {
        if (merged[k] === undefined || merged[k] === null || merged[k] === '') {
          merged[k] = fromUrl[k];
        }
      });
      merged.last_url = window.location.href;
      merged.last_visit_at = new Date().toISOString();
    } else {
      merged = Object.assign({}, fromUrl);
      merged.landing_url = window.location.href;
      merged.referrer = document.referrer || null;
      merged.user_agent = navigator.userAgent;
      merged.first_visit_at = new Date().toISOString();
      merged.last_url = window.location.href;
      merged.last_visit_at = merged.first_visit_at;
    }

    merged._expires = nowMs() + ttlMs();
    writeStorage(merged);
    return merged;
  }

  function getTrackingParams() {
    var saved = readStorage();
    if (saved) return saved;
    return capture();
  }

  // Expor globalmente
  window.getTrackingParams = getTrackingParams;
  window.captureTrackingParams = capture;

  // Captura imediatamente ao carregar
  try { capture(); } catch (_) {}
})();
