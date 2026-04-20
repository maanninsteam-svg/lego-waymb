<?php
/**
 * checkout.php — Página dedicada de finalização de compra
 *
 * Substitui o modal que estava em loja.php. Recebe o estado do carrinho via sessionStorage
 * (chave `esco_checkout_state`), mantendo o mesmo `checkout_id` para dedup perfeita entre
 * os eventos disparados na loja, no checkout e no gateway.
 *
 * Integração (tudo usa os mesmos endpoints do fluxo anterior):
 *  - pixel_init.php → carrega params_capture.js e o TikTok Pixel
 *  - tracking.php   → UTMify (waiting_payment/paid) + TikTok CAPI v1.3
 *  - create-transaction.php → proxy WayMB (criar + status)
 *  - /api/tracking  → endpoint externo que gera código de rastreio de envio
 *
 * Fluxo:
 *  1. onload: valida state, renderiza resumo, dispara InitiateCheckout (browser + CAPI).
 *  2. selectPM: dispara AddPaymentInfo (browser + CAPI).
 *  3. submitPayment: POST create-transaction → tracking waiting_payment → abre overlay de status → polling.
 *  4. paid: CompletePayment (browser + CAPI), UTMify paid, await tracking.php, redireciona para UP1_ENTRY (central_config) com UTMs.
 *  5. failed: permite retry sem perder o formulário.
 */
require_once __DIR__ . '/central.php';

$up1_entry_url = central_redirect('up1_entry', '/up1');
if ($up1_entry_url === '') {
    $up1_entry_url = '/up1';
}
if (substr($up1_entry_url, -1) !== '/') {
    $up1_entry_url .= '/';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <?php include 'pixel_init.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout — LEGO World Cup 2026</title>
  <meta name="description" content="Finaliza a tua compra de forma segura — LEGO FIFA World Cup 2026.">
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300..700&family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --bg: #ffffff;
      --bg-soft: #F9FAFB;
      --bg-card: #ffffff;
      --border: #E5E7EB;
      --border-soft: #F3F4F6;
      --text: #1a1d26;
      --text-soft: #6B7280;
      --text-muted: #9CA3AF;
      --yellow: #f5c518;
      --yellow-dark: #EAB308;
      --yellow-soft: #FFFBEB;
      --outline-fine: 1px solid rgba(26, 29, 38, 0.14);
      --outline-fine-strong: 1px solid rgba(26, 29, 38, 0.22);
      --red: #DC2626;
      --blue: #2563EB;
      --blue-soft: #EFF6FF;
      --green: #16A34A;
      --green-dark: #15803D;
      --radius: 14px;
      --radius-sm: 10px;
      --radius-lg: 18px;
      --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
      --shadow-md: 0 4px 14px rgba(0,0,0,.08), 0 2px 6px rgba(0,0,0,.05);
      --shadow-lg: 0 10px 30px rgba(0,0,0,.12), 0 4px 10px rgba(0,0,0,.08);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Nunito', sans-serif;
      background: #f7f7f5;
      color: var(--text);
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }

    h1, h2, h3 { font-family: 'Fredoka', sans-serif; letter-spacing: -0.01em; }

    /* ═════ Header padrão (igual ao da loja) ═════ */
    .header {
      position: sticky;
      top: 0;
      z-index: 50;
      background: #f5c518;
      box-shadow: 0 4px 12px rgba(0,0,0,.2);
    }
    .header-inner {
      max-width: 1200px;
      margin: 0 auto;
      padding: 12px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    .header .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
    .header .logo img { height: 38px; }
    .header .nav-links { display: flex; gap: 22px; }
    .header .nav-links a {
      text-decoration: none;
      color: #1a1d26;
      font-weight: 700;
      font-size: 14px;
      transition: color .15s;
    }
    .header .nav-links a:hover { color: #ffffff; }
    @media (max-width: 720px) { .header .nav-links { display: none; } }

    /* ═════ Icon bag (reutilizável) ═════ */
    .icon-bag {
      display: inline-block;
      width: 1em;
      height: 1.25em;
      vertical-align: -0.2em;
      background-color: currentColor;
      -webkit-mask: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 20'><g fill='black' fill-rule='evenodd'><path d='M4 3.512v5.804c0 .377.349.684.779.684.43 0 .779-.307.779-.684V3.512C5.558 2.33 6.653 1.368 8 1.368c1.347 0 2.442.962 2.442 2.144v5.804c0 .377.35.684.78.684.43 0 .778-.307.778-.684V3.512C12 1.575 10.206 0 8 0S4 1.575 4 3.512z'/><path d='M2.46 6.33c-.269 0-.489.194-.5.441L1.435 18.19a.436.436 0 00.131.332.52.52 0 00.348.149h12.151c.276 0 .501-.207.501-.462l-.525-11.436c-.011-.248-.23-.442-.5-.442H2.46zM14.448 20l-12.974-.001a1.591 1.591 0 01-1.064-.462 1.357 1.357 0 01-.408-1.03L.56 6.372C.595 5.602 1.277 5 2.11 5h11.78c.835 0 1.516.602 1.551 1.372l.56 12.197c0 .789-.697 1.431-1.553 1.431z'/></g></svg>") no-repeat center / contain;
              mask: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 20'><g fill='black' fill-rule='evenodd'><path d='M4 3.512v5.804c0 .377.349.684.779.684.43 0 .779-.307.779-.684V3.512C5.558 2.33 6.653 1.368 8 1.368c1.347 0 2.442.962 2.442 2.144v5.804c0 .377.35.684.78.684.43 0 .778-.307.778-.684V3.512C12 1.575 10.206 0 8 0S4 1.575 4 3.512z'/><path d='M2.46 6.33c-.269 0-.489.194-.5.441L1.435 18.19a.436.436 0 00.131.332.52.52 0 00.348.149h12.151c.276 0 .501-.207.501-.462l-.525-11.436c-.011-.248-.23-.442-.5-.442H2.46zM14.448 20l-12.974-.001a1.591 1.591 0 01-1.064-.462 1.357 1.357 0 01-.408-1.03L.56 6.372C.595 5.602 1.277 5 2.11 5h11.78c.835 0 1.516.602 1.551 1.372l.56 12.197c0 .789-.697 1.431-1.553 1.431z'/></g></svg>") no-repeat center / contain;
    }

    /* ═════ Checkout page layout ═════ */
    .checkout-page {
      max-width: 1200px;
      margin: 0 auto;
      padding: 28px 20px 80px;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--text-soft);
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 14px;
      transition: color .15s;
    }
    .back-link:hover { color: var(--text); }

    .page-title {
      font-size: 36px;
      margin: 0 0 6px;
      font-weight: 700;
    }
    .page-subtitle {
      color: var(--text-soft);
      margin: 0 0 22px;
      font-size: 15px;
    }

    /* ─── Bloco principal (stepper + passos) ─── */
    .checkout-flow-card {
      padding: 0 0 20px;
      overflow: hidden;
    }
    .checkout-flow-card .progress-wrap {
      margin: 0 0 20px;
      padding: 14px 20px;
      background: #fff;
      border-radius: 999px;
      border: var(--outline-fine);
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    }
    .progress-steps {
      list-style: none;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 4px;
      flex-wrap: nowrap;
      width: 100%;
      margin: 0;
      padding: 4px 0 2px;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
    }
    .progress-steps::-webkit-scrollbar { height: 4px; }
    .progress-steps::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 2px; }
    .progress-step {
      display: flex;
      align-items: center;
      gap: 6px;
      min-width: 0;
      flex: 0 0 auto;
    }
    .progress-bubble {
      flex-shrink: 0;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      font-weight: 800;
      font-family: 'Nunito', sans-serif;
      line-height: 1;
    }
    .progress-step--done .progress-bubble {
      background: linear-gradient(145deg, #22c55e, var(--green));
      color: #fff;
      border: var(--outline-fine);
      box-shadow: 0 2px 8px rgba(22, 163, 74, 0.35);
    }
    .progress-step--done .progress-bubble i { font-size: 13px; }
    .progress-step--current .progress-bubble {
      background: linear-gradient(180deg, #fde047 0%, var(--yellow) 55%, var(--yellow-dark) 100%);
      color: var(--text);
      border: var(--outline-fine-strong);
      box-shadow: 0 4px 14px rgba(234, 179, 8, 0.45);
    }
    .progress-step--todo .progress-bubble {
      background: #F3F4F6;
      color: var(--text-muted);
      border: 1px solid #E5E7EB;
    }
    .progress-text {
      font-size: 13px;
      font-weight: 800;
      color: var(--text-soft);
      letter-spacing: 0.02em;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .progress-step--done .progress-text { color: var(--green-dark); }
    .progress-step--current .progress-text {
      color: var(--text);
      font-weight: 800;
    }
    .progress-step--todo .progress-text { color: var(--text-muted); font-weight: 700; }
    .progress-connector {
      flex: 1 1 8px;
      min-width: 6px;
      max-width: 48px;
      height: 3px;
      border-radius: 2px;
      background: linear-gradient(90deg, #D1D5DB, #E5E7EB);
      align-self: center;
    }
    @media (max-width: 560px) {
      .progress-text { font-size: 10px; max-width: 72px; white-space: normal; line-height: 1.15; }
      .progress-bubble { width: 30px; height: 30px; font-size: 11px; }
      .progress-step { gap: 4px; }
    }

    .step-panel { display: none; animation: stepIn .22s ease-out; }
    .step-panel.is-active { display: block; }
    @keyframes stepIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    .step-panel-head {
      margin-bottom: 16px;
      padding-bottom: 12px;
      border-bottom: 1px solid rgba(26, 29, 38, 0.08);
    }
    .step-panel-head h3 {
      font-family: 'Nunito', sans-serif;
      font-size: 1.15rem;
      font-weight: 600;
      margin: 0 0 4px;
      color: var(--text);
      display: flex;
      align-items: center;
      gap: 10px;
      letter-spacing: -0.02em;
    }
    .step-panel-head p {
      margin: 0;
      font-size: 13px;
      color: var(--text-soft);
      line-height: 1.45;
    }
    .card-icon-brand {
      color: #b45309;
      font-size: 1.1rem;
    }
    /* Ícones dos passos: mesmo amarelo da bolinha ativa do stepper */
    .step-panel-head .card-icon-brand {
      color: var(--yellow-dark);
    }

    .step-nav-bar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-top: 20px;
      padding-top: 16px;
      border-top: 1px dashed rgba(26, 29, 38, 0.1);
    }
    .btn-step-secondary {
      padding: 12px 18px;
      border-radius: 14px;
      font-weight: 800;
      font-size: 14px;
      cursor: pointer;
      font-family: inherit;
      border: var(--outline-fine);
      background: #fff;
      color: var(--text-soft);
      transition: .15s;
    }
    .btn-step-secondary:hover { background: var(--bg-soft); color: var(--text); }
    .btn-step-primary {
      margin-left: auto;
      padding: 12px 22px;
      border-radius: 14px;
      font-weight: 800;
      font-size: 15px;
      cursor: pointer;
      font-family: inherit;
      border: var(--outline-fine-strong);
      background: linear-gradient(180deg, #fde047 0%, var(--yellow) 100%);
      color: var(--text);
      box-shadow: 0 4px 14px rgba(234, 179, 8, 0.35);
      transition: .15s;
    }
    .btn-step-primary:hover { filter: brightness(1.03); transform: translateY(-1px); }
    .btn-step-primary:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    /* ─── Layout checkout (coluna única, resumo no topo) ─── */
    .checkout-layout {
      display: grid;
      grid-template-columns: 1fr;
      gap: 24px;
      align-items: start;
      max-width: 800px;
      margin: 0 auto;
    }

    /* ─── Cards ─── */
    .card {
      background: var(--bg-card);
      border: 1px solid rgba(26, 29, 38, 0.1);
      border-radius: 18px;
      padding: 22px 24px;
      margin-bottom: 16px;
      box-shadow: 0 2px 14px rgba(0, 0, 0, 0.05);
    }
    .card-title {
      font-size: 17px;
      font-weight: 700;
      margin: 0 0 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-family: 'Nunito', sans-serif;
    }
    .card-title i { font-size: 18px; }

    /* ─── Inputs ─── */
    .field { margin-bottom: 12px; }
    .field-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    @media (max-width: 640px) { .field-row { grid-template-columns: 1fr; } }

    .field-label {
      display: block;
      font-size: 12.5px;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 6px;
      letter-spacing: 0.01em;
    }
    .field-label .optional { color: var(--text-muted); font-weight: 500; font-size: 11.5px; }

    .field-input, .field-select {
      width: 100%;
      padding: 12px 14px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      font-size: 14.5px;
      font-family: inherit;
      background: #fff;
      color: var(--text);
      transition: border-color .15s, box-shadow .15s;
    }
    .field-input::placeholder { color: var(--text-muted); }
    .field-input:focus, .field-select:focus {
      outline: none;
      border-color: var(--yellow-dark);
      box-shadow: 0 0 0 3px rgba(245, 197, 24, 0.35);
    }

    /* MBWay: prefixo PT +351 + número (só dígitos nacionais no input) */
    .phone-input-combined {
      display: flex;
      align-items: stretch;
      width: 100%;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      background: #fff;
      overflow: hidden;
      transition: border-color .15s, box-shadow .15s;
    }
    .field.error .phone-input-combined {
      border-color: #fb923c;
      background: #fffbeb;
    }
    .phone-input-combined:focus-within {
      border-color: var(--yellow-dark);
      box-shadow: 0 0 0 3px rgba(245, 197, 24, 0.35);
    }
    .phone-input-prefix {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      padding: 0 12px 0 14px;
      font-size: 14px;
      font-weight: 600;
      color: #64748b;
      letter-spacing: 0.02em;
      background: #f8fafc;
      border-right: 1px solid rgba(26, 29, 38, 0.12);
      white-space: nowrap;
    }
    .phone-input-prefix strong {
      font-weight: 700;
      color: #334155;
      margin-left: 6px;
    }
    .phone-input-main {
      flex: 1;
      min-width: 0;
      border: none;
      border-radius: 0;
      padding: 12px 14px;
      font-size: 14.5px;
      font-family: inherit;
      background: #fff;
      color: var(--text);
    }
    .phone-input-main::placeholder {
      color: #94a3b8;
    }
    .phone-input-main:focus {
      outline: none;
    }
    .field.error .field-input, .field.error .field-select {
      border-color: #fb923c;
      background: #fffbeb;
    }
    .field-err {
      display: none;
      font-size: 12.5px;
      color: #9a3412;
      margin-top: 8px;
      font-weight: 600;
      line-height: 1.45;
      padding: 10px 12px;
      border-radius: 14px;
      background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
      border: 1px solid rgba(251, 146, 60, 0.45);
      align-items: flex-start;
      gap: 10px;
    }
    .field-err > .field-err-icon {
      flex-shrink: 0;
      margin-top: 2px;
      font-size: 14px;
      color: #c2410c;
    }
    .field-err > .field-err-msg {
      flex: 1;
      min-width: 0;
    }
    .field.error .field-err { display: flex; }

    .postal-lookup-msg {
      font-size: 12.5px;
      font-weight: 600;
      margin-top: 8px;
      padding: 8px 10px;
      border-radius: 10px;
      line-height: 1.35;
    }
    .postal-lookup-msg--ok {
      background: #ecfdf5;
      color: #047857;
      border: 1px solid #a7f3d0;
    }
    .postal-lookup-msg--warn {
      background: #fffbeb;
      color: #92400e;
      border: 1px solid #fde68a;
    }
    .postal-lookup-msg--err {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }

    /* ─── Payment methods ─── */
    .pm-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    @media (max-width: 480px) { .pm-grid { grid-template-columns: 1fr; } }

    .pm-card {
      padding: 16px;
      border: 2px solid var(--border);
      border-radius: var(--radius-sm);
      cursor: pointer;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      background: #fff;
      transition: all .18s;
      position: relative;
    }
    .pm-card:hover {
      border-color: rgba(234, 179, 8, 0.55);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(234, 179, 8, 0.12);
    }
    .pm-card.selected {
      border-color: var(--yellow-dark);
      background: var(--yellow-soft);
      box-shadow: 0 0 0 1px rgba(26, 29, 38, 0.12), 0 4px 16px rgba(234, 179, 8, 0.22);
    }
    .pm-card.selected::after {
      content: '';
      position: absolute;
      top: 10px;
      right: 10px;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: var(--yellow-dark);
      -webkit-mask: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='white' d='M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'/></svg>") center/14px no-repeat;
              mask: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='white' d='M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'/></svg>") center/14px no-repeat;
    }
    .pm-icon { font-size: 28px; margin: 4px 0; }
    .pm-label { font-weight: 800; font-size: 14px; }
    .pm-sub { font-size: 11.5px; color: var(--text-soft); text-align: center; }

    .pm-method-section.is-hidden {
      display: none !important;
    }
    .pm-method-summary {
      margin-bottom: 14px;
    }
    .pm-method-summary-text {
      margin: 0;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
      color: var(--text);
      padding: 12px 14px;
      border-radius: var(--radius-sm);
      background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
      border: 1px solid #a7f3d0;
    }
    .pm-method-summary-text .fa-circle-check {
      color: #059669;
      font-size: 18px;
    }

    /* Desbloqueio: véu verde → revela miniaturas + pagar */
    .pm-unlock-block {
      margin-top: 4px;
      overflow: hidden;
    }
    .bump-unlock-card {
      margin-bottom: 14px;
      position: relative;
      overflow: hidden;
    }
    .bump-unlock-card-inner {
      position: relative;
      z-index: 1;
    }
    .bump-unlock-veil {
      position: absolute;
      inset: 0;
      z-index: 5;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 12px;
      padding: 20px;
      text-align: center;
      background: linear-gradient(160deg, #34d399 0%, #10b981 28%, #059669 62%, #047857 100%);
      color: #ecfdf5;
      box-shadow: inset 0 0 80px rgba(6, 95, 70, 0.35);
      transform: translateY(0);
      will-change: transform;
    }
    .bump-unlock-veil-icon {
      font-size: 2.25rem;
      filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.15));
      animation: bumpVeilPulse 1.1s ease-in-out infinite;
    }
    .bump-unlock-veil-text {
      font-size: 15px;
      font-weight: 800;
      letter-spacing: 0.03em;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
    }
    @keyframes bumpVeilPulse {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.06); opacity: 0.92; }
    }
    .bump-unlock-veil.is-revealing {
      animation: bumpVeilSlideUp 1.05s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }
    @keyframes bumpVeilSlideUp {
      from { transform: translateY(0); }
      to { transform: translateY(-102%); }
    }
    .bump-unlock-veil.bump-unlock-veil--done {
      transform: translateY(-102%);
      visibility: hidden;
      pointer-events: none;
    }
    @media (prefers-reduced-motion: reduce) {
      .bump-unlock-veil-icon { animation: none; }
      .bump-unlock-veil.is-revealing {
        animation: none;
      }
      .bump-unlock-veil.bump-unlock-veil--done,
      .bump-unlock-veil.is-revealing {
        transform: translateY(-102%);
        visibility: hidden;
      }
    }
    .pm-unlock-block.is-unlocking .bump-unlock-card {
      animation: bumpCardEnter 0.45s ease-out;
    }
    @keyframes bumpCardEnter {
      from {
        opacity: 0.96;
        transform: translateY(10px);
        box-shadow: 0 0 0 rgba(16, 185, 129, 0);
      }
      to {
        opacity: 1;
        transform: none;
        box-shadow: 0 2px 14px rgba(0, 0, 0, 0.06);
      }
    }
    .pm-unlock-block:not(.is-unlocked) .pay-cta-block {
      visibility: hidden;
    }
    .pm-unlock-block.is-unlocked .pay-cta-block {
      visibility: visible;
      animation: payCtaFade 0.4s ease-out forwards;
    }
    @keyframes payCtaFade {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: none; }
    }
    .pm-unlock-block.is-unlocked .bump-card {
      animation: bumpTileReveal 0.5s ease backwards;
    }
    .pm-unlock-block.is-unlocked .bump-card:nth-child(1) { animation-delay: 0.08s; }
    .pm-unlock-block.is-unlocked .bump-card:nth-child(2) { animation-delay: 0.16s; }
    .pm-unlock-block.is-unlocked .bump-card:nth-child(3) { animation-delay: 0.24s; }
    .pm-unlock-block.is-unlocked .bump-card:nth-child(4) { animation-delay: 0.32s; }
    @keyframes bumpTileReveal {
      from {
        opacity: 0;
        transform: translateY(10px) scale(0.97);
      }
      to {
        opacity: 1;
        transform: none;
      }
    }
    @media (prefers-reduced-motion: reduce) {
      .pm-unlock-block.is-unlocked .bump-card { animation: none; }
    }
    .bump-decline-row {
      margin-top: 14px;
      padding-top: 12px;
      border-top: 1px dashed rgba(26, 29, 38, 0.12);
    }
    .btn-bump-decline {
      width: 100%;
      padding: 12px 16px;
      border-radius: 14px;
      font-size: 14px;
      font-weight: 700;
      font-family: 'Nunito', sans-serif;
      cursor: pointer;
      border: 1.5px solid var(--border);
      background: #fff;
      color: var(--text-soft);
      transition: background .15s, border-color .15s, color .15s;
    }
    .btn-bump-decline:hover {
      background: var(--bg-soft);
      border-color: rgba(26, 29, 38, 0.18);
      color: var(--text);
    }
    .pay-cta-block {
      margin-top: 4px;
    }
    .pay-cta-block .btn-pay-hint {
      margin-top: 0;
      margin-bottom: 6px;
    }
    .pay-cta-block .btn-pay {
      margin-top: 0;
    }

    /* ─── Bumps ─── */
    .bump-section-head {
      display: flex;
      align-items: center;
      gap: 8px 12px;
      flex-wrap: nowrap;
      margin: 0 0 14px;
      min-width: 0;
    }
    .bump-head-icon {
      flex-shrink: 0;
      font-size: 17px;
      color: var(--yellow-dark);
      filter: drop-shadow(0 1px 0 rgba(0, 0, 0, 0.06));
    }
    .bump-section-title {
      flex: 1 1 auto;
      min-width: 0;
      font-family: 'Nunito', sans-serif;
      font-size: clamp(11.5px, 2.35vw, 14px);
      font-weight: 700;
      line-height: 1.25;
      letter-spacing: -0.02em;
      color: var(--text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .bump-offer-pulse {
      flex-shrink: 0;
      margin-left: auto;
      padding: 0;
      border: none;
      background: none;
      font-family: Georgia, 'Times New Roman', serif;
      font-size: 10px;
      font-weight: 600;
      font-style: italic;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: #92400e;
      cursor: default;
      animation: bumpOfferPulse 2.4s ease-in-out infinite;
    }
    @keyframes bumpOfferPulse {
      0%, 100% {
        opacity: 0.72;
        text-shadow: none;
        transform: scale(1);
      }
      50% {
        opacity: 1;
        color: #b45309;
        text-shadow:
          0 0 10px rgba(234, 179, 8, 0.55),
          0 0 22px rgba(212, 175, 55, 0.35);
        transform: scale(1.03);
      }
    }
    @media (max-width: 360px) {
      .bump-section-title {
        white-space: normal;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        font-size: 11px;
      }
    }

    .bumps-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
      gap: 10px;
    }
    .bump-card {
      padding: 10px 8px 8px;
      border: 2px solid var(--border);
      border-radius: var(--radius-sm);
      cursor: pointer;
      text-align: center;
      background: #fff;
      transition: all .18s;
      position: relative;
    }
    .bump-card:hover { border-color: var(--yellow-dark); transform: translateY(-2px); }
    .bump-card.selected {
      border-color: var(--yellow-dark);
      background: var(--yellow-soft);
      box-shadow: 0 0 0 1px rgba(26, 29, 38, 0.1);
    }
    .bump-card.selected::before {
      content: '';
      position: absolute;
      top: 6px;
      right: 6px;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: var(--yellow-dark);
      z-index: 2;
      -webkit-mask: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='white' d='M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'/></svg>") center/14px no-repeat;
              mask: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='white' d='M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'/></svg>") center/14px no-repeat;
    }
    .bump-card-media {
      position: relative;
      min-height: 78px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-end;
      margin-bottom: 4px;
    }
    .bump-rare-tag {
      display: inline-block;
      align-self: center;
      margin-bottom: 5px;
      padding: 0 2px;
      font-family: 'Nunito', sans-serif;
      font-size: 8.5px;
      font-weight: 900;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: #b8860b;
      text-shadow:
        0 0 8px rgba(234, 179, 8, 0.45),
        0 1px 0 rgba(120, 53, 15, 0.25);
      animation: bumpRareGold 2s ease-in-out infinite;
    }
    @keyframes bumpRareGold {
      0%, 100% {
        color: #92400e;
        text-shadow:
          0 0 6px rgba(180, 83, 9, 0.35),
          0 1px 0 rgba(67, 20, 7, 0.2);
      }
      50% {
        color: #eab308;
        text-shadow:
          0 0 14px rgba(253, 224, 71, 0.75),
          0 0 24px rgba(234, 179, 8, 0.4),
          0 1px 0 rgba(120, 53, 15, 0.15);
      }
    }
    .bump-card img {
      width: 74px;
      height: 74px;
      object-fit: contain;
      display: block;
      margin: 0 auto;
    }
    .bump-name { font-size: 12px; font-weight: 700; margin-bottom: 2px; line-height: 1.2; }
    .bump-price { font-size: 13px; color: #15803D; font-weight: 800; }

    /* ─── CTA (amarelo marca + contorno fino) ─── */
    .btn-pay {
      width: 100%;
      padding: 17px 24px;
      background: linear-gradient(180deg, #fde047 0%, var(--yellow) 45%, var(--yellow-dark) 100%);
      color: var(--text);
      border: var(--outline-fine-strong);
      border-radius: 16px;
      font-size: 17px;
      font-weight: 800;
      cursor: pointer;
      font-family: inherit;
      letter-spacing: .01em;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-top: 12px;
      box-shadow: 0 8px 22px rgba(234, 179, 8, 0.4);
      transition: transform .18s, box-shadow .18s;
    }
    .btn-pay:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 12px 28px rgba(234, 179, 8, 0.5);
    }
    .btn-pay:active:not(:disabled) { transform: translateY(0); }
    .btn-pay:disabled {
      opacity: .45;
      cursor: not-allowed;
      filter: grayscale(0.15);
      box-shadow: none;
    }
    .btn-pay-hint {
      text-align: center;
      font-size: 12.5px;
      color: var(--text-soft);
      margin-top: 8px;
      font-weight: 600;
      line-height: 1.4;
    }
    .spinner {
      width: 18px;
      height: 18px;
      border: 2.5px solid rgba(26, 29, 38, 0.15);
      border-top-color: var(--text);
      border-radius: 50%;
      animation: spin .7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .trust-bar {
      display: flex;
      justify-content: center;
      gap: 22px;
      margin-top: 14px;
      flex-wrap: wrap;
      font-size: 12px;
      color: var(--text-soft);
    }
    .trust-bar span { display: inline-flex; align-items: center; gap: 5px; font-weight: 600; }
    .trust-bar i { color: var(--yellow-dark); }

    /* ─── Error banner (só visível com mensagem; [hidden] não pode perder para display:flex) ─── */
    .error-banner {
      background: #FEF2F2;
      border: 1.5px solid var(--red);
      color: var(--red);
      padding: 12px 16px;
      border-radius: var(--radius-sm);
      font-weight: 700;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
    }
    .error-banner[hidden] {
      display: none !important;
    }
    .error-banner > i { flex-shrink: 0; }
    .error-banner-text { flex: 1; min-width: 0; }

    /* ─── Resumo do pedido (topo, fluxo normal — sem sticky) ─── */
    .summary {
      position: static;
      background: var(--bg-card);
      border: 1px solid rgba(26, 29, 38, 0.1);
      border-radius: 18px;
      padding: 20px;
      margin-bottom: 16px;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
    }
    .summary-header {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      margin-bottom: 14px;
    }
    .summary-title {
      font-size: 16px;
      font-weight: 800;
      margin: 0;
      font-family: 'Nunito', sans-serif;
    }
    .summary-count { font-size: 12.5px; color: var(--text-soft); font-weight: 700; }

    .summary-items {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 14px;
      max-height: 320px;
      overflow-y: auto;
      padding-right: 4px;
    }
    .summary-items::-webkit-scrollbar { width: 6px; }
    .summary-items::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

    .sum-item {
      display: flex;
      gap: 10px;
      padding: 8px;
      background: var(--bg-soft);
      border-radius: var(--radius-sm);
      align-items: center;
    }
    .sum-item img {
      width: 50px;
      height: 50px;
      object-fit: contain;
      background: #fff;
      border-radius: 6px;
      padding: 3px;
      border: 1px solid var(--border);
    }
    .sum-item-info { flex: 1; min-width: 0; }
    .sum-item-name {
      font-size: 12.5px;
      font-weight: 700;
      margin: 0 0 2px;
      line-height: 1.25;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }
    .sum-item-meta {
      font-size: 11.5px;
      color: var(--text-soft);
      display: flex;
      justify-content: space-between;
    }
    .sum-item-price { font-weight: 800; color: var(--text); }
    .sum-item-price-wrap {
      display: flex;
      align-items: baseline;
      justify-content: flex-end;
      flex-wrap: wrap;
      gap: 6px;
    }
    .sum-item-old {
      text-decoration: line-through;
      color: #9CA3AF;
      font-size: 11px;
      font-weight: 600;
    }

    .summary-totals {
      border-top: 1.5px dashed var(--border);
      padding-top: 12px;
    }
    .sum-row {
      display: flex;
      justify-content: space-between;
      font-size: 14px;
      color: var(--text-soft);
      padding: 4px 0;
    }
    .sum-row.total {
      font-size: 22px;
      font-weight: 800;
      color: var(--text);
      margin-top: 8px;
      padding-top: 12px;
      border-top: 2px solid rgba(26, 29, 38, 0.12);
    }
    .sum-row.total span:last-child {
      color: #b45309;
    }
    .sum-row .shipping-free { color: var(--green); font-weight: 800; }

    /* ─── Result overlay (pós-submit) ─── */
    .result-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.55);
      z-index: 999;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      animation: fadeIn .3s;
      overflow-y: auto;
    }
    /* O atributo hidden perde para .result-overlay { display:flex } sem isto — overlay vazio visível ao carregar */
    .result-overlay[hidden] {
      display: none !important;
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

    .result-modal {
      background: #fff;
      border-radius: var(--radius-lg);
      max-width: 460px;
      width: 100%;
      padding: 28px;
      box-shadow: var(--shadow-lg);
      text-align: center;
      animation: slideUp .3s ease-out;
      max-height: 92vh;
      overflow-y: auto;
    }
    .result-icon-big {
      font-size: 56px;
      margin-bottom: 10px;
      line-height: 1;
    }
    .result-title {
      font-size: 24px;
      font-weight: 800;
      margin: 0 0 8px;
    }
    .result-subtitle {
      color: var(--text-soft);
      margin: 0 0 20px;
      font-size: 15px;
      line-height: 1.5;
    }

    .ref-box {
      background: var(--bg-soft);
      border-radius: var(--radius-sm);
      padding: 4px 16px;
      margin-bottom: 16px;
    }
    .ref-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid var(--border);
      font-size: 14px;
    }
    .ref-row:last-child { border-bottom: 0; }
    .ref-label { color: var(--text-soft); font-weight: 600; }
    .ref-val { font-weight: 800; font-family: 'Nunito', monospace; letter-spacing: .02em; }

    .status-box {
      padding: 14px;
      background: #FFFBEB;
      border: 1.5px solid #FCD34D;
      border-radius: var(--radius-sm);
      margin-top: 12px;
      font-weight: 700;
      font-size: 14px;
      color: #92400E;
      display: flex;
      align-items: center;
      gap: 10px;
      justify-content: center;
    }
    .status-box.success {
      background: #F0FDF4;
      border-color: #86EFAC;
      color: #166534;
    }
    .status-box.error {
      background: #FEF2F2;
      border-color: #FCA5A5;
      color: #B91C1C;
    }

    .result-btn-row {
      display: flex;
      gap: 10px;
      margin-top: 18px;
    }
    .result-btn {
      flex: 1;
      padding: 12px 18px;
      border-radius: var(--radius-sm);
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      border: 1.5px solid var(--border);
      background: #fff;
      color: var(--text);
      font-family: inherit;
      transition: .15s;
    }
    .result-btn:hover { background: var(--bg-soft); }
    .result-btn.primary {
      background: linear-gradient(180deg, #fde047 0%, var(--yellow) 100%);
      border: var(--outline-fine-strong);
      color: var(--text);
    }
    .result-btn.primary:hover { background: var(--yellow-dark); color: #fff; border-color: #ca8a04; }

    /* ─── Mobile ajustes ─── */
    @media (max-width: 640px) {
      .checkout-page { padding: 20px 14px 60px; }
      .page-title { font-size: 26px; }
      .btn-pay { padding: 16px; font-size: 16px; }
      .sum-row.total { font-size: 18px; }
      .card { padding: 18px; }
    }
  </style>
</head>

<body>

  <!-- ═════ Header padrão da loja ═════ -->
  <header class="header">
    <div class="header-inner">
      <a href="loja.php" class="logo">
        <img src="./loja_files/logolego.png" alt="Brick World Cup 2026">
      </a>
      <nav class="nav-links">
        <a href="loja.php#produtos">Produtos</a>
        <a href="loja.php#categorias">Categorias</a>
        <a href="loja.php#lancamentos">Lançamentos</a>
        <a href="loja.php#promocoes">Promoções</a>
      </nav>
      <a href="loja.php" aria-label="Voltar à loja" style="display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:50%;background:transparent;text-decoration:none;color:#1a1d26;font-size:18px;transition:background-color .2s;" onmouseover="this.style.background='rgba(0,0,0,.08)'" onmouseout="this.style.background='transparent'">
        <i class="fa-solid fa-arrow-left"></i>
      </a>
    </div>
  </header>

  <!-- ═════ Main checkout ═════ -->
  <main class="checkout-page">
    <a href="loja.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Voltar à loja</a>

    <h1 class="page-title">Finalizar Compra</h1>
    <p class="page-subtitle">Estás a um passo da tua coleção LEGO FIFA World Cup 2026</p>

    <div class="checkout-layout">
      <!-- ═════ Coluna esquerda: formulário ═════ -->
      <section class="checkout-main">
        <div class="summary">
          <div class="summary-header">
            <h3 class="summary-title">Resumo do Pedido</h3>
            <span class="summary-count" id="summaryCount">0 items</span>
          </div>
          <div class="summary-items" id="summaryItems"></div>
          <div class="summary-totals">
            <div class="sum-row"><span>Subtotal</span><span id="subtotalValue">€0.00</span></div>
            <div class="sum-row" id="bumpsRow" style="display:none"><span>Miniaturas</span><span id="bumpsValue">€0.00</span></div>
            <div class="sum-row"><span>Entrega</span><span class="shipping-free">Grátis</span></div>
            <div class="sum-row total"><span>Total</span><span id="totalValue">€0.00</span></div>
          </div>
        </div>

        <div class="error-banner" id="errorBanner" hidden></div>

        <!-- Fluxo em 3 passos (stepper + painéis) -->
        <div class="card checkout-flow-card">
          <div class="progress-wrap">
            <div class="progress-steps" role="list" aria-label="Etapas do checkout">
              <div id="stepNode1" class="progress-step progress-step--current" role="listitem">
                <span class="progress-bubble" aria-hidden="true">1</span>
                <span class="progress-text">Identificação</span>
              </div>
              <div class="progress-connector" aria-hidden="true"></div>
              <div id="stepNode2" class="progress-step progress-step--todo" role="listitem">
                <span class="progress-bubble" aria-hidden="true">2</span>
                <span class="progress-text">Morada</span>
              </div>
              <div class="progress-connector" aria-hidden="true"></div>
              <div id="stepNode3" class="progress-step progress-step--todo" role="listitem">
                <span class="progress-bubble" aria-hidden="true">3</span>
                <span class="progress-text">Pagamento</span>
              </div>
            </div>
          </div>

          <!-- Passo 1 -->
          <div id="stepPanel1" class="step-panel is-active" role="tabpanel" aria-labelledby="stepNode1">
            <div class="step-panel-head">
              <h3><i class="fa-solid fa-id-card card-icon-brand" aria-hidden="true"></i> Identificação</h3>
              <p>Os teus dados para contacto. Vamos tratar disto com carinho.</p>
            </div>
            <div class="field">
              <label class="field-label" for="ck-name">Nome completo</label>
              <input class="field-input" type="text" id="ck-name" placeholder="João Silva" autocomplete="name" required>
              <div class="field-err"><i class="fa-solid fa-circle-info field-err-icon" aria-hidden="true"></i><span class="field-err-msg">Precisamos do teu nome completo para identificar a encomenda.</span></div>
            </div>
            <div class="field">
              <label class="field-label" for="ck-email">Email</label>
              <input class="field-input" type="email" id="ck-email" placeholder="exemplo@mail.com" autocomplete="email" required>
              <div class="field-err"><i class="fa-solid fa-circle-info field-err-icon" aria-hidden="true"></i><span class="field-err-msg">Indica um email válido para receberes a confirmação.</span></div>
            </div>
            <div class="field">
              <label class="field-label" for="ck-phone">Número MBway</label>
              <div class="phone-input-combined">
                <span class="phone-input-prefix" aria-hidden="true">PT <strong>+351</strong></span>
                <input class="phone-input-main" type="tel" id="ck-phone" name="phone" placeholder="Introduza o seu número MBway" autocomplete="tel-national" inputmode="numeric" maxlength="9" required aria-label="Número de telemóvel português, 9 dígitos após o indicativo +351">
              </div>
              <div class="field-err"><i class="fa-solid fa-circle-info field-err-icon" aria-hidden="true"></i><span class="field-err-msg">Introduz os 9 dígitos do teu número MBWay.</span></div>
            </div>
          </div>

          <!-- Passo 2 -->
          <div id="stepPanel2" class="step-panel" role="tabpanel" aria-labelledby="stepNode2">
            <div class="step-panel-head">
              <h3><i class="fa-solid fa-truck-fast card-icon-brand" aria-hidden="true"></i> Morada de entrega</h3>
            </div>
            <input type="hidden" id="ck-country" value="" autocomplete="off">
            <div class="field">
              <label class="field-label" for="ck-postal">Código postal</label>
              <input class="field-input" type="text" id="ck-postal" placeholder="Código postal" maxlength="22" autocomplete="postal-code" required>
              <div class="field-err"><i class="fa-solid fa-circle-info field-err-icon" aria-hidden="true"></i><span class="field-err-msg">Indica um código postal válido.</span></div>
              <div id="postalLookupMsg" class="postal-lookup-msg" hidden></div>
            </div>
            <div class="field" id="postalPickWrap" hidden>
              <label class="field-label" for="postalPick">Localidade</label>
              <select class="field-select" id="postalPick">
                <option value="">—</option>
              </select>
            </div>
            <div class="field">
              <label class="field-label" for="ck-address">Morada</label>
              <input class="field-input" type="text" id="ck-address" placeholder="Rua, número, andar" autocomplete="address-line1" required>
              <div class="field-err"><i class="fa-solid fa-circle-info field-err-icon" aria-hidden="true"></i><span class="field-err-msg">Indica a rua e o número para enviarmos a encomenda.</span></div>
            </div>
            <div class="field-row">
              <div class="field">
                <label class="field-label" for="ck-city">Localidade</label>
                <input class="field-input" type="text" id="ck-city" placeholder="Cidade" autocomplete="address-level2" required>
                <div class="field-err"><i class="fa-solid fa-circle-info field-err-icon" aria-hidden="true"></i><span class="field-err-msg">Preenche a cidade ou localidade.</span></div>
              </div>
              <div class="field">
                <label class="field-label" for="ck-district">Distrito / região <span class="optional">(opcional)</span></label>
                <input class="field-input" type="text" id="ck-district" placeholder="" autocomplete="address-level1">
              </div>
            </div>
          </div>

          <!-- Passo 3 -->
          <div id="stepPanel3" class="step-panel" role="tabpanel" aria-labelledby="stepNode3">
            <div id="pmMethodSection" class="pm-method-section">
              <div class="step-panel-head">
                <h3><i class="fa-solid fa-wallet card-icon-brand" aria-hidden="true"></i> Método de pagamento</h3>
                <p>Toca num método para continuar — a seguir podes adicionar uma miniatura (opcional) e confirmar o pagamento.</p>
              </div>
              <div class="pm-grid">
                <div class="pm-card" id="pm-mbway" onclick="selectPM('mbway')" role="button" tabindex="0">
                  <div class="pm-icon"><i class="fa-solid fa-mobile-screen" style="color:#b45309"></i></div>
                  <div class="pm-label">MBWay</div>
                  <div class="pm-sub">Confirmação via app</div>
                </div>
                <div class="pm-card" id="pm-multibanco" onclick="selectPM('multibanco')" role="button" tabindex="0">
                  <div class="pm-icon"><i class="fa-solid fa-building-columns" style="color:#78716c"></i></div>
                  <div class="pm-label">Multibanco</div>
                  <div class="pm-sub">Referência • ATM ou app bancária</div>
                </div>
              </div>
            </div>

            <div id="pmMethodSummary" class="pm-method-summary" hidden>
              <p class="pm-method-summary-text">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span>Pagamento: <strong id="pmMethodSummaryLabel">MBWay</strong></span>
              </p>
            </div>

            <div id="pmUnlockBlock" class="pm-unlock-block" hidden>
              <div class="card bump-unlock-card" id="bumpUnlockCard">
                <div class="bump-unlock-veil" id="bumpUnlockVeil" aria-hidden="true">
                  <span class="bump-unlock-veil-icon"><i class="fa-solid fa-gift" aria-hidden="true"></i></span>
                  <span class="bump-unlock-veil-text">A desbloquear oferta…</span>
                </div>
                <div class="bump-unlock-card-inner">
                  <h2 class="bump-section-head">
                    <i class="fa-solid fa-gift bump-head-icon" aria-hidden="true"></i>
                    <span class="bump-section-title">Adiciona uma Miniatura ao teu pedido</span>
                    <span class="bump-offer-pulse">Oferta especial</span>
                  </h2>
                  <div class="bumps-grid" id="bumpsGrid"></div>
                  <div class="bump-decline-row">
                    <button type="button" class="btn-bump-decline" id="btnBumpDecline">Não quero — continuar para pagamento</button>
                  </div>
                </div>
              </div>
              <div id="payCtaBlock" class="pay-cta-block" hidden>
                <p class="btn-pay-hint" id="btnPayHint">Escolhe um método de pagamento em cima.</p>
                <button type="button" class="btn-pay" id="btnPay" disabled onclick="submitPayment()">
                  <i class="fa-solid fa-lock"></i> Pagar agora seguro — €<span id="btnPayTotal">0.00</span>
                </button>
              </div>
            </div>
          </div>

          <div class="step-nav-bar">
            <button type="button" class="btn-step-secondary" id="btnStepBack" hidden>Voltar</button>
            <button type="button" class="btn-step-primary" id="btnStepNext">Continuar →</button>
          </div>
        </div>

        <div class="trust-bar">
          <span><i class="fa-solid fa-shield-halved"></i> Pagamento seguro</span>
          <span><i class="fa-solid fa-lock"></i> SSL encriptado</span>
          <span><i class="fa-solid fa-truck-fast"></i> Entrega grátis</span>
        </div>
      </section>
    </div>
  </main>

  <!-- ═════ Result overlay (pós-submit) ═════ -->
  <div class="result-overlay" id="resultOverlay" hidden>
    <div class="result-modal" id="resultModal">
      <div id="resultContent"></div>
    </div>
  </div>

  <script>
    /* ═════════════════════════════════════════════════════════════════════
       Checkout controller
       Lê state, renderiza, integra com tracking.php + create-transaction.php
    ═══════════════════════════════════════════════════════════════════════*/
    (function(){
      'use strict';

      var UP1_ENTRY = <?php echo json_encode($up1_entry_url, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

      // ── Load state ─────────────────────────────────────────────────────
      let state = null;
      try {
        const raw = sessionStorage.getItem('esco_checkout_state');
        state = raw ? JSON.parse(raw) : null;
      } catch (_) { state = null; }

      if (!state || !Array.isArray(state.cart) || state.cart.length === 0) {
        // Carrinho vazio → volta para a loja
        window.location.replace('loja.php');
        return;
      }

      const cart = state.cart;
      const checkout_id = state.checkout_id || ('CH-' + Date.now() + Math.random().toString(36).substr(2, 9));
      window.checkout_id = checkout_id;

      let selectedMethod = '';
      var paymentUnlockShown = false;

      const bumps = {
        messi:  { name: 'Miniatura Messi',   price: 9.90, img: './loja_files/MESSIMINIATURA.png',  selected: false, rare: true },
        cr7:    { name: 'Miniatura CR7',     price: 9.90, img: './loja_files/CR7MINIATURA.png',    selected: false, rare: true },
        vini:   { name: 'Miniatura Vini Jr.', price: 4.90, img: './loja_files/VINIMINIATURA.png',   selected: false, rare: false },
        mbappe: { name: 'Miniatura Mbappé',  price: 4.90, img: './loja_files/MBAPPEMINIATURA.png', selected: false, rare: false }
      };

      const $ = (sel) => document.querySelector(sel);
      const $$ = (sel) => document.querySelectorAll(sel);

      function escapeHtml(s) {
        if (s == null || s === '') return '';
        return String(s)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }
      function safeImgSrc(src) {
        const s = (src && String(src).trim()) || './loja_files/logolego.png';
        return s.replace(/"/g, '');
      }

      let checkoutStep = 1;
      var addPaymentInfoSent = false;

      function setStepNodeState(node, state, num) {
        if (!node) return;
        node.className = 'progress-step progress-step--' + state;
        const b = node.querySelector('.progress-bubble');
        if (!b) return;
        if (state === 'done') b.innerHTML = '<i class="fa-solid fa-check"></i>';
        else b.textContent = String(num);
      }

      function updateStepperVisual() {
        const n1 = $('#stepNode1');
        const n2 = $('#stepNode2');
        const n3 = $('#stepNode3');
        if (checkoutStep === 1) {
          setStepNodeState(n1, 'current', 1);
          setStepNodeState(n2, 'todo', 2);
          setStepNodeState(n3, 'todo', 3);
        } else if (checkoutStep === 2) {
          setStepNodeState(n1, 'done');
          setStepNodeState(n2, 'current', 2);
          setStepNodeState(n3, 'todo', 3);
        } else {
          setStepNodeState(n1, 'done');
          setStepNodeState(n2, 'done');
          setStepNodeState(n3, 'current', 3);
        }
      }

      function phoneNationalDigits() {
        return ($('#ck-phone').value || '').replace(/\D/g, '');
      }
      function phoneFullInternational() {
        const d = phoneNationalDigits();
        return d.length >= 9 ? '+351' + d.slice(0, 9) : '';
      }

      function countryCode() {
        return (($('#ck-country').value || '') + '').trim().toUpperCase();
      }

      function isPostalFormatOk(cp) {
        cp = (cp || '').trim();
        if (cp.length < 4 || cp.length > 22) return false;
        var cc = countryCode();
        if (cc === 'PT') {
          return /^\d{4}-\d{3}$/.test(cp);
        }
        return /^[A-Za-z0-9\-\s]+$/.test(cp);
      }

      var postalLookupResults = [];
      var postalLookupSeq = 0;
      var postalLookupDebounce = null;

      function applyPostalResult(r) {
        if (!r) return;
        var postal = (r.postal || '').trim();
        if (postal) $('#ck-postal').value = postal;
        $('#ck-address').value = (r.address_line || '').trim();
        $('#ck-city').value = (r.city || '').trim();
        $('#ck-district').value = (r.district || '').trim();
        var cc = (r.country_code || '').trim().toUpperCase();
        $('#ck-country').value = cc;
        updatePayButtonState();
      }

      async function runPostalLookup() {
        var mySeq = ++postalLookupSeq;
        var input = $('#ck-postal');
        var msgEl = $('#postalLookupMsg');
        var pickWrap = $('#postalPickWrap');
        var pick = $('#postalPick');
        if (!input || !msgEl || !pickWrap || !pick) return;
        var q = (input.value || '').trim();
        msgEl.hidden = true;
        pickWrap.hidden = true;
        postalLookupResults = [];
        pick.innerHTML = '<option value="">—</option>';
        if (q.length < 4) {
          msgEl.hidden = true;
          return;
        }
        try {
          var res = await fetch('postal-lookup.php?q=' + encodeURIComponent(q));
          if (mySeq !== postalLookupSeq) return;
          var data = await res.json();
          if (mySeq !== postalLookupSeq) return;
          if (!data.ok) {
            msgEl.hidden = true;
            return;
          }
          var results = data.results || [];
          if (results.length === 0) {
            msgEl.hidden = true;
            return;
          }
          postalLookupResults = results;
          if (results.length === 1) {
            applyPostalResult(results[0]);
            msgEl.hidden = true;
            return;
          }
          results.forEach(function (r, i) {
            var opt = document.createElement('option');
            opt.value = String(i);
            var t = r.label || (r.city + ', ' + r.country_name);
            if (t.length > 100) t = t.slice(0, 97) + '…';
            opt.textContent = t;
            pick.appendChild(opt);
          });
          pickWrap.hidden = false;
          msgEl.hidden = true;
        } catch (e) {
          if (mySeq !== postalLookupSeq) return;
          msgEl.textContent = 'Tenta novamente.';
          msgEl.className = 'postal-lookup-msg postal-lookup-msg--err';
          msgEl.hidden = false;
        }
      }

      function schedulePostalLookup() {
        clearTimeout(postalLookupDebounce);
        postalLookupDebounce = setTimeout(function () {
          runPostalLookup();
        }, 520);
      }

      function validateAllQuiet() {
        const name = ($('#ck-name').value || '').trim();
        if (!name) return false;
        const email = ($('#ck-email').value || '').trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return false;
        const phone = phoneNationalDigits();
        if (phone.length < 9) return false;
        if (($('#ck-address').value || '').trim() === '') return false;
        const cp = ($('#ck-postal').value || '').trim();
        if (!isPostalFormatOk(cp)) return false;
        if (($('#ck-city').value || '').trim() === '') return false;
        return true;
      }

      function updatePayButtonState() {
        const btn = $('#btnPay');
        const hint = $('#btnPayHint');
        if (!btn) return;
        const dataOk = validateAllQuiet();
        const ok = checkoutStep === 3 && dataOk && paymentUnlockShown && selectedMethod;
        btn.disabled = !ok;
        if (hint) {
          if (checkoutStep < 3) {
            hint.textContent = 'Completa os passos em cima para chegares ao pagamento.';
          } else if (!selectedMethod) {
            hint.textContent = 'Escolhe MBWay ou Multibanco para continuar.';
          } else if (!paymentUnlockShown) {
            hint.textContent = 'A carregar…';
          } else if (!dataOk) {
            hint.textContent = 'Falta algum dado — volta aos passos anteriores.';
          } else {
            hint.textContent = 'Confirma o pagamento — ou usa “Não quero” se não quiseres miniaturas.';
          }
        }
      }

      function resetPaymentStep3UI() {
        var ms = $('#pmMethodSection');
        var sum = $('#pmMethodSummary');
        var ub = $('#pmUnlockBlock');
        var pb = $('#payCtaBlock');
        var veil = $('#bumpUnlockVeil');
        if (ms) ms.classList.remove('is-hidden');
        if (sum) sum.hidden = true;
        if (ub) {
          ub.hidden = true;
          ub.classList.remove('is-unlocking', 'is-unlocked');
        }
        if (pb) pb.hidden = true;
        if (veil) {
          veil.classList.remove('is-revealing', 'bump-unlock-veil--done');
          veil.style.visibility = '';
        }
        paymentUnlockShown = false;
        selectedMethod = '';
        $$('.pm-card').forEach(function (c) { c.classList.remove('selected'); });
      }

      function setCheckoutStep(s) {
        var prevStep = checkoutStep;
        checkoutStep = s;
        if (prevStep === 3 && s === 2) {
          resetPaymentStep3UI();
        }
        [1, 2, 3].forEach(function (i) {
          const p = document.getElementById('stepPanel' + i);
          if (!p) return;
          var on = i === s;
          p.classList.toggle('is-active', on);
          p.setAttribute('aria-hidden', on ? 'false' : 'true');
        });
        updateStepperVisual();
        var back = $('#btnStepBack');
        var next = $('#btnStepNext');
        if (back) back.hidden = s === 1;
        if (next) {
          if (s === 3) {
            next.style.display = 'none';
            if (paymentUnlockShown) {
              var ms3 = $('#pmMethodSection');
              var sum3 = $('#pmMethodSummary');
              var lbl3 = $('#pmMethodSummaryLabel');
              if (ms3) ms3.classList.add('is-hidden');
              if (sum3) sum3.hidden = false;
              if (lbl3 && selectedMethod) {
                lbl3.textContent = selectedMethod === 'mbway' ? 'MBWay' : 'Multibanco';
              }
              var ub = $('#pmUnlockBlock');
              var pb = $('#payCtaBlock');
              var veil3 = $('#bumpUnlockVeil');
              if (ub) {
                ub.hidden = false;
                ub.classList.add('is-unlocked');
                ub.classList.remove('is-unlocking');
              }
              if (pb) pb.hidden = false;
              if (veil3) {
                veil3.classList.remove('is-revealing');
                veil3.classList.add('bump-unlock-veil--done');
              }
            }
          } else {
            next.style.display = '';
            next.textContent = 'Continuar →';
          }
        }
        updatePayButtonState();
        var flow = document.querySelector('.checkout-flow-card');
        if (flow) flow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }

      // ── Helpers ────────────────────────────────────────────────────────
      function selectedBumps() {
        return Object.entries(bumps).filter(([,b]) => b.selected).map(([key,b]) => ({ key, ...b }));
      }
      function allItems() {
        return [
          ...cart,
          ...selectedBumps().map(b => ({ id: 'bump-' + b.key, name: b.name, quantity: 1, price: b.price, img: b.img }))
        ];
      }
      function computeTotals() {
        const subtotal = cart.reduce((s, i) => s + i.price * i.quantity, 0);
        const bumpsTotal = selectedBumps().reduce((s,b) => s + b.price, 0);
        return { subtotal, bumpsTotal, total: subtotal + bumpsTotal };
      }

      // ── Render ─────────────────────────────────────────────────────────
      function renderSummary() {
        const itemsEl = $('#summaryItems');
        itemsEl.innerHTML = '';
        const items = allItems();
        items.forEach(item => {
          const q = item.quantity || 1;
          const lineTotal = item.price * q;
          const isBump = String(item.id).indexOf('bump-') === 0;
          const hasOld = !isBump && typeof item.oldPrice === 'number' && item.oldPrice > item.price;
          const oldLine = hasOld ? `<span class="sum-item-old">€${(item.oldPrice * q).toFixed(2)}</span>` : '';

          const div = document.createElement('div');
          div.className = 'sum-item';
          div.innerHTML = `
            <img src="${escapeHtml(safeImgSrc(item.img))}" alt="${escapeHtml(item.name)}">
            <div class="sum-item-info">
              <p class="sum-item-name">${escapeHtml(item.name)}</p>
              <div class="sum-item-meta">
                <span>Qty: ${q}</span>
                <span class="sum-item-price-wrap">
                  ${oldLine}
                  <span class="sum-item-price">€${lineTotal.toFixed(2)}</span>
                </span>
              </div>
            </div>`;
          itemsEl.appendChild(div);
        });
        const totalCount = items.reduce((s,i) => s + i.quantity, 0);
        $('#summaryCount').textContent = totalCount + (totalCount === 1 ? ' item' : ' items');
        updateTotals();
      }

      function updateTotals() {
        const { subtotal, bumpsTotal, total } = computeTotals();
        $('#subtotalValue').textContent = '€' + subtotal.toFixed(2);
        $('#bumpsRow').style.display = bumpsTotal > 0 ? '' : 'none';
        $('#bumpsValue').textContent = '€' + bumpsTotal.toFixed(2);
        $('#totalValue').textContent = '€' + total.toFixed(2);
        $('#btnPayTotal').textContent = total.toFixed(2);
        return total;
      }

      function renderBumps() {
        const grid = $('#bumpsGrid');
        grid.innerHTML = '';
        Object.entries(bumps).forEach(([key, b]) => {
          const card = document.createElement('div');
          card.className = 'bump-card';
          card.id = 'bump-' + key;
          card.setAttribute('role', 'button');
          card.setAttribute('tabindex', '0');
          card.onclick = () => toggleBump(key);
          card.onkeydown = (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleBump(key); } };
          const rareTag = b.rare
            ? '<span class="bump-rare-tag" aria-label="Colecionável raro">Raro</span>'
            : '';
          card.innerHTML = `
            <div class="bump-card-media">
              ${rareTag}
              <img src="${b.img}" alt="${escapeHtml(b.name)}">
            </div>
            <p class="bump-name">${escapeHtml(b.name)}</p>
            <p class="bump-price">+€${b.price.toFixed(2)}</p>`;
          grid.appendChild(card);
        });
      }

      function toggleBump(key) {
        bumps[key].selected = !bumps[key].selected;
        $('#bump-' + key).classList.toggle('selected', bumps[key].selected);
        renderSummary();
        updatePayButtonState();
      }
      window.toggleBump = toggleBump;

      function unlockPaymentSection() {
        var block = $('#pmUnlockBlock');
        var payBlock = $('#payCtaBlock');
        var veil = $('#bumpUnlockVeil');
        if (!block || !payBlock) return;

        function fireAddPaymentInfoOnce() {
          if (!addPaymentInfoSent) {
            addPaymentInfoSent = true;
            var tTotal = computeTotals().total;
            if (window.ttq) {
              ttq.track('AddPaymentInfo', {
                contents: allItems().map(function (i) {
                  return { content_id: String(i.id), content_name: i.name, quantity: i.quantity, price: i.price };
                }),
                value: tTotal,
                currency: 'EUR',
                event_id: checkout_id
              });
            }
            serverTrack('add_payment_info', checkout_id, tTotal, buildTrackingCustomer());
          }
        }

        function afterVeilReveal() {
          if (veil) {
            veil.classList.remove('is-revealing');
            veil.classList.add('bump-unlock-veil--done');
          }
          block.classList.remove('is-unlocking');
          block.classList.add('is-unlocked');
          payBlock.hidden = false;
          paymentUnlockShown = true;
          updatePayButtonState();
          updateTotals();
          var card = $('#bumpUnlockCard');
          if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          fireAddPaymentInfoOnce();
        }

        if (!paymentUnlockShown) {
          block.hidden = false;
          block.classList.remove('is-unlocked');
          block.classList.add('is-unlocking');
          payBlock.hidden = true;
          renderBumps();

          var revealOnce = false;
          function finishReveal() {
            if (revealOnce) return;
            revealOnce = true;
            afterVeilReveal();
          }

          if (veil) {
            veil.classList.remove('bump-unlock-veil--done', 'is-revealing');
            void veil.offsetWidth;
            function onVeilAnimEnd(e) {
              if (!e || e.target !== veil) return;
              veil.removeEventListener('animationend', onVeilAnimEnd);
              finishReveal();
            }
            veil.addEventListener('animationend', onVeilAnimEnd);
            veil.classList.add('is-revealing');
            var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reducedMotion) {
              veil.removeEventListener('animationend', onVeilAnimEnd);
              setTimeout(finishReveal, 80);
            } else {
              setTimeout(function () {
                if (revealOnce) return;
                veil.removeEventListener('animationend', onVeilAnimEnd);
                finishReveal();
              }, 1400);
            }
          } else {
            setTimeout(finishReveal, 400);
          }
        } else {
          payBlock.hidden = false;
          block.classList.add('is-unlocked');
          if (veil) {
            veil.classList.remove('is-revealing');
            veil.classList.add('bump-unlock-veil--done');
          }
          updatePayButtonState();
        }
      }

      function selectPM(method) {
        selectedMethod = method;
        $$('.pm-card').forEach(function (c) { c.classList.remove('selected'); });
        var el = $('#pm-' + method);
        if (el) el.classList.add('selected');
        var ms = $('#pmMethodSection');
        var sum = $('#pmMethodSummary');
        var lbl = $('#pmMethodSummaryLabel');
        if (ms) ms.classList.add('is-hidden');
        if (sum) sum.hidden = false;
        if (lbl) lbl.textContent = method === 'mbway' ? 'MBWay' : 'Multibanco';
        unlockPaymentSection();
        updatePayButtonState();
      }
      window.selectPM = selectPM;

      function declineBumpsAndPay() {
        Object.keys(bumps).forEach(function (k) { bumps[k].selected = false; });
        renderBumps();
        renderSummary();
        submitPayment();
      }
      window.declineBumpsAndPay = declineBumpsAndPay;

      // ── Payload alinhado aos formulários (nome, email, telefone, morada, etc.) ──
      function buildTrackingCustomer() {
        return {
          name: ($('#ck-name') && $('#ck-name').value) ? $('#ck-name').value.trim() : '',
          email: ($('#ck-email') && $('#ck-email').value) ? $('#ck-email').value.trim() : '',
          phone: typeof phoneFullInternational === 'function' ? phoneFullInternational() : '',
          country: typeof countryCode === 'function' ? (countryCode() || 'PT') : 'PT',
          address: ($('#ck-address') && $('#ck-address').value) ? $('#ck-address').value.trim() : '',
          postal: ($('#ck-postal') && $('#ck-postal').value) ? $('#ck-postal').value.trim() : '',
          city: ($('#ck-city') && $('#ck-city').value) ? $('#ck-city').value.trim() : '',
          district: ($('#ck-district') && $('#ck-district').value) ? $('#ck-district').value.trim() : ''
        };
      }

      // ── Server-side tracking (UTMify + TikTok CAPI via tracking.php) ──
      function serverTrack(status, orderId, amount, customer) {
        const tp = (typeof window.getTrackingParams === 'function') ? window.getTrackingParams() : {};
        const items = allItems().map(i => ({
          id: String(i.id),
          name: i.name,
          quantity: i.quantity || 1,
          price: i.price
        }));
        const cust = customer || buildTrackingCustomer();
        const methodForTrack = (status === 'waiting_payment' || status === 'paid') ? selectedMethod : (selectedMethod || 'checkout');
        return fetch('tracking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            status: status,
            orderId: orderId,
            transaction_id: orderId,
            amount: amount,
            customer: cust,
            items: items,
            method: methodForTrack,
            trackingParams: tp
          })
        }).then(function (r) { return r.json(); }).catch(function (err) {
          console.warn('tracking error:', err);
          return { ok: false, error: String(err) };
        });
      }

      function buildUp1RedirectUrl() {
        var base = (typeof UP1_ENTRY === 'string' && UP1_ENTRY) ? UP1_ENTRY : '/up1/';
        if (base.charAt(0) !== '/') base = '/' + base.replace(/^\/+/, '');
        if (base.slice(-1) !== '/') base += '/';
        var tp = (typeof window.getTrackingParams === 'function') ? window.getTrackingParams() : {};
        var keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'ttclid', 'ttp', 'fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'src', 'sck'];
        var qs = [];
        keys.forEach(function (k) {
          if (tp[k]) qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(tp[k])));
        });
        return base + (qs.length ? ('?' + qs.join('&')) : '');
      }

      // ── Validação ──────────────────────────────────────────────────────
      function setFieldError(id, hasError, msg) {
        const el = document.getElementById(id);
        if (!el) return;
        const wrap = el.closest('.field');
        if (!wrap) return;
        wrap.classList.toggle('error', hasError);
        if (msg) {
          const err = wrap.querySelector('.field-err');
          if (err) {
            const msgEl = err.querySelector('.field-err-msg');
            if (msgEl) msgEl.textContent = msg;
            else err.textContent = msg;
          }
        }
      }

      function validateStep1() {
        var ok = true;
        var name = ($('#ck-name').value || '').trim();
        if (!name) {
          setFieldError('ck-name', true, 'Precisamos do teu nome completo para identificar a encomenda.');
          ok = false;
        } else setFieldError('ck-name', false);

        var email = ($('#ck-email').value || '').trim();
        if (!email) {
          setFieldError('ck-email', true, 'Indica o teu email — enviamos a confirmação para lá.');
          ok = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          setFieldError('ck-email', true, 'Este email não parece válido. Verifica se está completo.');
          ok = false;
        } else setFieldError('ck-email', false);

        var phone = phoneNationalDigits();
        if (phone.length < 9) {
          setFieldError('ck-phone', true, 'Introduz os 9 dígitos do teu número MBWay.');
          ok = false;
        } else setFieldError('ck-phone', false);

        return ok;
      }

      function validateStep2() {
        var ok = true;
        var addr = ($('#ck-address').value || '').trim();
        if (!addr) {
          setFieldError('ck-address', true, 'Indica rua, número e andar para a entrega.');
          ok = false;
        } else setFieldError('ck-address', false);

        var cp = ($('#ck-postal').value || '').trim();
        if (!cp) {
          setFieldError('ck-postal', true, 'O código postal é obrigatório.');
          ok = false;
        } else if (!isPostalFormatOk(cp)) {
          var ccP = countryCode();
          var pmsg = ccP === 'PT'
            ? 'Em Portugal usa o formato 0000-000 (ex.: 1000-001).'
            : (ccP === '' ? 'Código postal inválido.' : 'Código postal inválido para o país detetado.');
          setFieldError('ck-postal', true, pmsg);
          ok = false;
        } else setFieldError('ck-postal', false);

        var city = ($('#ck-city').value || '').trim();
        if (!city) {
          setFieldError('ck-city', true, 'Diz-nos a cidade ou localidade.');
          ok = false;
        } else setFieldError('ck-city', false);

        return ok;
      }

      function validate() {
        var a = validateStep1();
        if (!a) {
          setCheckoutStep(1);
          return false;
        }
        var b = validateStep2();
        if (!b) {
          setCheckoutStep(2);
          return false;
        }
        return true;
      }

      function showBanner(msg) {
        const b = $('#errorBanner');
        if (!b) return;
        b.innerHTML = '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span class="error-banner-text"></span>';
        const t = b.querySelector('.error-banner-text');
        if (t) t.textContent = msg;
        b.hidden = false;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
      function hideBanner() { $('#errorBanner').hidden = true; }

      // ── Submit ─────────────────────────────────────────────────────────
      async function submitPayment() {
        hideBanner();
        if (!validate()) {
          showBanner('Quase! Corrige os campos em destaque — estamos quase a enviar a tua encomenda.');
          return;
        }
        if (!selectedMethod || (selectedMethod !== 'mbway' && selectedMethod !== 'multibanco')) {
          showBanner('Escolhe MBWay ou Multibanco antes de pagar.');
          return;
        }

        const customer = {
          name: $('#ck-name').value.trim(),
          email: $('#ck-email').value.trim(),
          phone: phoneFullInternational(),
          address: $('#ck-address').value.trim(),
          postal: $('#ck-postal').value.trim(),
          city: $('#ck-city').value.trim(),
          district: $('#ck-district').value.trim(),
          country: countryCode() || 'PT'
        };

        const { total } = computeTotals();
        const btn = $('#btnPay');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner"></div> A processar…';

        try {
          const res = await fetch('create-transaction.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              payer: {
                name: customer.name,
                email: customer.email,
                phone: customer.phone
              },
              amount: total,
              method: selectedMethod,
              shipping: {
                address: customer.address,
                postal: customer.postal,
                city: customer.city,
                district: customer.district,
                country: customer.country
              }
            })
          });
          const data = await res.json();

          if (!res.ok) {
            showBanner(data.error || 'Erro ao processar pagamento. Tenta novamente.');
            resetPayBtn();
            return;
          }

          const transactionId = data.id || data.transaction_id;
          if (!transactionId) {
            showBanner('Gateway não retornou ID da transação.');
            resetPayBtn();
            return;
          }

          // Tracking waiting_payment (salva pending .utmify + TikTok PlaceAnOrder)
          serverTrack('waiting_payment', transactionId, total, buildTrackingCustomer());

          // Abre overlay de status e inicia polling
          showWaitingResult(data, total, transactionId, customer);
          pollPayment(transactionId, total, customer);

        } catch (err) {
          console.error('submit err', err);
          showBanner('Erro de ligação. Tenta novamente.');
          resetPayBtn();
        }
      }
      window.submitPayment = submitPayment;

      function resetPayBtn() {
        const btn = $('#btnPay');
        const total = computeTotals().total;
        btn.innerHTML = '<i class="fa-solid fa-lock"></i> Pagar agora seguro — €<span id="btnPayTotal">' + total.toFixed(2) + '</span>';
        updatePayButtonState();
      }

      // ── Result overlay ─────────────────────────────────────────────────
      function showWaitingResult(data, total, txnId, customer) {
        const content = $('#resultContent');
        let head = '';
        if (selectedMethod === 'multibanco' && data.referenceData) {
          const rd = data.referenceData;
          head = `
            <div class="result-icon-big"><i class="fa-solid fa-building-columns" style="color:#6B7280"></i></div>
            <h2 class="result-title">Referência gerada!</h2>
            <p class="result-subtitle">Usa os dados abaixo para pagar no Multibanco ou na tua app bancária.</p>
            <div class="ref-box">
              <div class="ref-row"><span class="ref-label">Entidade</span><span class="ref-val">${rd.entity || '–'}</span></div>
              <div class="ref-row"><span class="ref-label">Referência</span><span class="ref-val">${rd.reference || '–'}</span></div>
              <div class="ref-row"><span class="ref-label">Montante</span><span class="ref-val">€${total.toFixed(2)}</span></div>
              ${rd.expiration ? `<div class="ref-row"><span class="ref-label">Validade</span><span class="ref-val">${rd.expiration}</span></div>` : ''}
            </div>`;
        } else {
          head = `
            <div class="result-icon-big"><i class="fa-solid fa-mobile-screen" style="color:#2563EB"></i></div>
            <h2 class="result-title">Pedido MBWay enviado!</h2>
            <p class="result-subtitle">Abre a app MBWay no teu telemóvel e aceita o pedido de pagamento de <strong>€${total.toFixed(2)}</strong>.</p>`;
        }
        content.innerHTML = head + `
          <div class="status-box" id="statusBox">
            <i class="fa-solid fa-hourglass-half"></i>
            <span>A aguardar confirmação do pagamento…</span>
          </div>`;
        $('#resultOverlay').hidden = false;
      }

      function pollPayment(transactionId, total, customer) {
        let attempts = 0;
        const MAX = 100; // ~5 min (3s × 100)
        const poll = async () => {
          if (attempts >= MAX) {
            setStatus('error', 'fa-triangle-exclamation', 'Tempo esgotado. Verifica o teu banco e contacta o suporte.');
            return;
          }
          attempts++;
          try {
            const res = await fetch('create-transaction.php?action=status&id=' + encodeURIComponent(transactionId));
            const d = await res.json();
            if (d.status === 'paid' || d.status === 'COMPLETED') {
              onPaid(transactionId, total, customer);
            } else if (d.status === 'failed' || d.status === 'DECLINED') {
              onFailed(transactionId);
            } else {
              setTimeout(poll, 3000);
            }
          } catch (err) {
            console.warn('poll err', err);
            setTimeout(poll, 3000);
          }
        };
        setTimeout(poll, 3000);
      }

      function setStatus(kind, iconClass, text) {
        const box = $('#statusBox');
        if (!box) return;
        box.className = 'status-box' + (kind ? ' ' + kind : '');
        box.innerHTML = '<i class="fa-solid ' + iconClass + '"></i> <span>' + text + '</span>';
      }

      function onPaid(transactionId, total, customer) {
        setStatus('success', 'fa-circle-check', 'Pagamento confirmado! A sincronizar e a redirecionar…');

        const items = allItems();

        // TikTok CompletePayment (browser — dedup com CAPI via mesmo event_id)
        if (window.ttq) {
          ttq.track('CompletePayment', {
            contents: items.map(i => ({ content_id: String(i.id), content_name: i.name, quantity: i.quantity || 1, price: i.price })),
            value: total,
            currency: 'EUR',
            event_id: transactionId
          });
        }

        try {
          sessionStorage.setItem('esco_front_paid_ctx', JSON.stringify({
            transactionId: String(transactionId),
            amount: total,
            paidAt: new Date().toISOString(),
            source: 'front_checkout'
          }));
        } catch (_) {}

        const trackShipping = function () {
          try {
            fetch('/api/tracking', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                order_id: String(transactionId),
                customer_name: customer.name,
                customer_email: customer.email,
                products: items.map(i => ({ title: i.name, quantity: i.quantity || 1, price: i.price.toFixed(2) })),
                shipping_address: {
                  address1: customer.address,
                  city: customer.city,
                  province: customer.district,
                  country: customer.country,
                  zip: customer.postal
                }
              })
            }).then(r => r.json()).then(d => {
              if (d && d.tracking_code) {
                try {
                  sessionStorage.setItem('tracking_code', d.tracking_code);
                  sessionStorage.setItem('tracking_url', d.tracking_url || '');
                  sessionStorage.setItem('tracking_name', customer.name);
                } catch (_) {}
              }
            }).catch(function () {});
          } catch (_) {}
        };

        try { sessionStorage.removeItem('esco_checkout_state'); } catch (_) {}

        // UTMify paid + TikTok CAPI CompletePayment (server) antes do redirect
        serverTrack('paid', transactionId, total, buildTrackingCustomer())
          .then(function (tr) {
            if (tr && tr.ok === false) console.warn('tracking.php aviso:', tr);
          })
          .catch(function () {})
          .finally(function () {
            trackShipping();
            setTimeout(function () {
              window.location.href = buildUp1RedirectUrl();
            }, 1200);
          });
      }

      function onFailed(transactionId) {
        setStatus('error', 'fa-circle-xmark', 'Pagamento recusado. Podes tentar outro método.');
        // Retry button após 400ms
        setTimeout(() => {
          const content = $('#resultContent');
          if (content && !content.querySelector('.result-btn-row')) {
            const div = document.createElement('div');
            div.className = 'result-btn-row';
            div.innerHTML = `
              <button class="result-btn" onclick="closeResultOverlay()">Fechar</button>
              <button class="result-btn primary" onclick="closeResultOverlay(); document.getElementById('pm-multibanco').click();">Tentar Multibanco</button>`;
            content.appendChild(div);
          }
          resetPayBtn();
        }, 400);
      }

      window.closeResultOverlay = function() {
        $('#resultOverlay').hidden = true;
      };

      // ── Formatação / masking ──────────────────────────────────────────
      function formatPostalInput(e) {
        var cc = countryCode();
        if (cc === 'PT') {
          var v = e.target.value.replace(/\D/g, '').slice(0, 7);
          if (v.length > 4) v = v.slice(0, 4) + '-' + v.slice(4);
          e.target.value = v;
        }
      }

      function onPostalFieldInput(e) {
        formatPostalInput(e);
        schedulePostalLookup();
      }
      function formatMbwayNationalDigits(e) {
        let v = e.target.value.replace(/\D/g, '');
        if (v.length > 9 && v.indexOf('351') === 0) v = v.slice(3);
        if (v.length > 9) v = v.slice(0, 9);
        e.target.value = v;
      }

      // ── Init ──────────────────────────────────────────────────────────
      function init() {
        renderBumps();
        renderSummary();

        // CEP: máscara PT + pesquisa automática (debounce ao digitar; ao sair do campo pesquisa já)
        $('#ck-postal')?.addEventListener('input', onPostalFieldInput);
        $('#ck-postal')?.addEventListener('blur', function () {
          clearTimeout(postalLookupDebounce);
          runPostalLookup();
        });
        $('#ck-phone')?.addEventListener('input', formatMbwayNationalDigits);
        $('#postalPick')?.addEventListener('change', function () {
          var i = parseInt(this.value, 10);
          if (isNaN(i) || !postalLookupResults[i]) return;
          applyPostalResult(postalLookupResults[i]);
          var msgEl = $('#postalLookupMsg');
          if (msgEl) msgEl.hidden = true;
        });

        // Clear field error on input + atualiza botão pagar
        ['ck-name','ck-email','ck-phone','ck-address','ck-postal','ck-city','ck-district'].forEach(function (id) {
          const el = document.getElementById(id);
          if (!el) return;
          el.addEventListener('input', function () {
            const f = el.closest('.field');
            if (f && f.classList.contains('error')) f.classList.remove('error');
            updatePayButtonState();
          });
        });

        $('#btnStepNext')?.addEventListener('click', function () {
          if (checkoutStep === 1) {
            if (!validateStep1()) return;
            setCheckoutStep(2);
          } else if (checkoutStep === 2) {
            if (!validateStep2()) return;
            setCheckoutStep(3);
          }
        });
        $('#btnStepBack')?.addEventListener('click', function () {
          if (checkoutStep === 2) setCheckoutStep(1);
          else if (checkoutStep === 3) setCheckoutStep(2);
        });
        $('#btnBumpDecline')?.addEventListener('click', declineBumpsAndPay);

        setCheckoutStep(1);

        // Dispara InitiateCheckout (browser + CAPI) com event_id = checkout_id
        const total = computeTotals().total;
        if (window.ttq) {
          ttq.track('InitiateCheckout', {
            contents: allItems().map(i => ({ content_id: String(i.id), content_name: i.name, quantity: i.quantity || 1, price: i.price })),
            value: total,
            currency: 'EUR',
            event_id: checkout_id
          });
        }
        serverTrack('initiate_checkout', checkout_id, total, buildTrackingCustomer());
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
      } else {
        init();
      }
    })();
  </script>
</body>
</html>
