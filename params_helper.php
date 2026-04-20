<?php
/**
 * params_helper.php
 *
 * Helpers server-side para extrair parâmetros de tracking enviados pelo frontend
 * via body JSON (trackingParams) ou cookie esco_tp. Produz estrutura padronizada
 * usada tanto pela UTMify quanto pelo TikTok CAPI.
 */

/**
 * Extrai o array de parâmetros de tracking de um body decodificado.
 * Prioridade: $body['trackingParams'] > $body['tracking_params'] > cookie esco_tp > null
 */
function extract_tracking_params(array $body = []): array {
    $raw = null;

    if (isset($body['trackingParams']) && is_array($body['trackingParams'])) {
        $raw = $body['trackingParams'];
    } elseif (isset($body['tracking_params']) && is_array($body['tracking_params'])) {
        $raw = $body['tracking_params'];
    } elseif (!empty($_COOKIE['esco_tp'])) {
        $decoded = json_decode($_COOKIE['esco_tp'], true);
        if (is_array($decoded)) $raw = $decoded;
    }

    if (!is_array($raw)) $raw = [];

    // Funis up1/upN enviam `utms` — funde com first-touch
    if (isset($body['utms']) && is_array($body['utms'])) {
        foreach ($body['utms'] as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $ks = (string)$k;
            if (!isset($raw[$ks]) || $raw[$ks] === null || $raw[$ks] === '') {
                $raw[$ks] = (string)$v;
            }
        }
    }

    // Normalizar campos esperados
    $known = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'src', 'sck',
        'ttclid', 'ttp',
        'fbclid',
        'gclid', 'gbraid', 'wbraid',
        'msclkid',
        'ref', 'source', 'campaign',
        'landing_url', 'last_url', 'referrer', 'user_agent',
        'first_visit_at', 'last_visit_at'
    ];
    $out = [];
    foreach ($known as $k) {
        $out[$k] = isset($raw[$k]) && $raw[$k] !== '' ? (string)$raw[$k] : null;
    }
    return $out;
}

/**
 * Retorna apenas os parâmetros válidos para o campo trackingParameters da UTMify.
 * Campos aceitos pela doc: src, sck, utm_source, utm_campaign, utm_medium, utm_content, utm_term
 */
function utmify_tracking_parameters(array $tp): array {
    return [
        'src'          => $tp['src'] ?? null,
        'sck'          => $tp['sck'] ?? null,
        'utm_source'   => $tp['utm_source'] ?? null,
        'utm_campaign' => $tp['utm_campaign'] ?? null,
        'utm_medium'   => $tp['utm_medium'] ?? null,
        'utm_content'  => $tp['utm_content'] ?? null,
        'utm_term'     => $tp['utm_term'] ?? null,
    ];
}

/**
 * Retorna o IP público do cliente (considera proxies X-Forwarded-For, Cloudflare, etc).
 */
function client_ip(): ?string {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['HTTP_X_REAL_IP'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];
    foreach ($candidates as $cand) {
        if (!$cand) continue;
        // X-Forwarded-For pode ter lista: pega o primeiro
        $first = trim(explode(',', $cand)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    return null;
}

/**
 * User-Agent do cliente. Usa o enviado pelo JS (capturado no primeiro acesso)
 * quando disponível, senão cai para o header HTTP atual.
 */
function client_user_agent(array $tp = []): ?string {
    if (!empty($tp['user_agent'])) return (string)$tp['user_agent'];
    return $_SERVER['HTTP_USER_AGENT'] ?? null;
}

/**
 * URL da página onde o evento aconteceu (prefere last_url do tracking, fallback ao REQUEST_URI).
 */
function page_url(array $tp = []): ?string {
    if (!empty($tp['last_url'])) return (string)$tp['last_url'];
    if (!empty($tp['landing_url'])) return (string)$tp['landing_url'];
    if (!empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['REQUEST_URI'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    return null;
}

/**
 * Referrer (prefere o capturado no primeiro acesso, fallback ao HTTP_REFERER atual).
 */
function page_referrer(array $tp = []): ?string {
    if (!empty($tp['referrer'])) return (string)$tp['referrer'];
    return $_SERVER['HTTP_REFERER'] ?? null;
}
