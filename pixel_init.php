<?php
/**
 * pixel_init.php
 * Carrega as configurações do pixel TikTok e o inicializa no frontend.
 */

$tracking_config_path = __DIR__ . '/tracking_config.json';
$tracking_config = [];

if (file_exists($tracking_config_path)) {
    $tracking_config = json_decode(file_get_contents($tracking_config_path), true);
}

$tt_pixel_id = $tracking_config['tiktok']['pixel_id'] ?? '';

?>

<!-- Captura persistente de todos os parâmetros de tracking (UTMs, ttclid, fbclid, gclid, referrer, etc) -->
<script src="./params_capture.js"></script>

<!-- TikTok Pixel Code Start -->
<script>
  !function (w, d, t) {
    w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=r+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
    <?php if ($tt_pixel_id): ?>
    ttq.load('<?= $tt_pixel_id ?>');
    ttq.page();
    <?php endif; ?>
  }(window, document, 'ttq');
</script>
<!-- TikTok Pixel Code End -->
