<?php
/**
 * Código postal: Portugal via GEO API PT + Nominatim (fallback).
 * Rápido: GEO API em paralelo; se houver resultado PT oficial, não chama Nominatim.
 * Nominatim: poucas queries em paralelo, timeouts curtos.
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

const HTTP_TIMEOUT_S = 8;
const CONNECT_TIMEOUT_S = 3;

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$q = preg_replace('/\s+/u', ' ', $q);

if (mb_strlen($q) < 4) {
    echo json_encode(['ok' => false, 'error' => 'Indica pelo menos 4 caracteres.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (mb_strlen($q) > 24) {
    echo json_encode(['ok' => false, 'error' => 'Código postal demasiado longo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function curl_init_fast(string $url, array $extraHeaders = []) {
    $ch = curl_init($url);
    $h = array_merge([
        'Accept: application/json',
        'User-Agent: EscoCheckout/1.0 (postal lookup)',
    ], $extraHeaders);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => CONNECT_TIMEOUT_S,
        CURLOPT_TIMEOUT => HTTP_TIMEOUT_S,
        CURLOPT_HTTPHEADER => $h,
    ]);
    return $ch;
}

/** Vários GET em paralelo; devolve [ url => body string|false ]. */
function curl_multi_get(array $urls): array {
    if ($urls === []) {
        return [];
    }
    $mh = curl_multi_init();
    $map = [];
    foreach ($urls as $url) {
        $ch = curl_init_fast($url);
        curl_multi_add_handle($mh, $ch);
        $map[(int)$ch] = [$ch, $url];
    }
    $running = null;
    do {
        $mrc = curl_multi_exec($mh, $running);
        if ($running > 0) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running > 0 && $mrc === CURLM_OK);

    $out = [];
    foreach ($map as [$ch, $url]) {
        $body = curl_multi_getcontent($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        $out[$url] = ($code === 200 && $body !== false && $body !== '') ? $body : false;
    }
    curl_multi_close($mh);
    return $out;
}

function http_get_json(string $url, array $headers = []): ?array {
    $ch = curl_init_fast($url, $headers);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || $body === false || $body === '') {
        return null;
    }
    $json = json_decode($body, true);
    return is_array($json) ? $json : null;
}

function nominatim_url(array $params): string {
    return 'https://nominatim.openstreetmap.org/search?' . http_build_query($params);
}

function map_item(array $item): array {
    $a = isset($item['address']) && is_array($item['address']) ? $item['address'] : [];
    $hn = trim((string)($a['house_number'] ?? ''));
    $road = trim((string)($a['road'] ?? ''));
    $line = trim($hn . ' ' . $road);
    if ($line === '') {
        $line = trim((string)($a['pedestrian'] ?? $a['footway'] ?? $a['path'] ?? ''));
    }
    if ($line === '') {
        $line = trim((string)($a['neighbourhood'] ?? $a['suburb'] ?? $a['quarter'] ?? ''));
    }
    $city = (string)($a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? $a['city_district'] ?? '');
    $district = (string)($a['state_district'] ?? $a['state'] ?? $a['county'] ?? $a['region'] ?? '');
    $postcode = (string)($a['postcode'] ?? '');
    $cc = isset($a['country_code']) ? strtoupper((string)$a['country_code']) : '';
    $countryName = (string)($a['country'] ?? '');
    return [
        'label' => (string)($item['display_name'] ?? ''),
        'address_line' => $line,
        'city' => $city,
        'postal' => $postcode,
        'district' => $district,
        'country_code' => $cc,
        'country_name' => $countryName,
    ];
}

function map_geoapi(array $j): array {
    $street = '';
    if (!empty($j['partes']) && is_array($j['partes']) && isset($j['partes'][0]['Artéria'])) {
        $street = trim((string)$j['partes'][0]['Artéria']);
    }
    $city = (string)($j['Localidade'] ?? $j['Concelho'] ?? '');
    $cp = (string)($j['CP'] ?? '');
    $dist = (string)($j['Distrito'] ?? '');
    $label = trim($city . ($dist !== '' ? ', ' . $dist : '') . ', Portugal');
    return [
        'label' => $label,
        'address_line' => $street,
        'city' => $city,
        'postal' => $cp,
        'district' => $dist,
        'country_code' => 'PT',
        'country_name' => 'Portugal',
    ];
}

function parse_geoapi_body(string $body): ?array {
    $j = json_decode($body, true);
    if (!is_array($j) || !empty($j['erro']) || !empty($j['msg']) || empty($j['CP'])) {
        return null;
    }
    return map_geoapi($j);
}

function pt_postal_candidates(string $digits): array {
    if (!preg_match('/^\d+$/', $digits)) {
        return [];
    }
    $len = strlen($digits);
    $out = [];
    if ($len === 7) {
        $out[] = substr($digits, 0, 4) . '-' . substr($digits, 4, 3);
        return array_values(array_unique($out));
    }
    if ($len === 8) {
        $out[] = substr($digits, 0, 4) . '-' . substr($digits, 4, 3);
        $out[] = substr($digits, 1, 4) . '-' . substr($digits, 5, 3);
        return array_values(array_unique(array_filter($out)));
    }
    if ($len >= 9) {
        $max = $len - 7;
        for ($i = 0; $i <= $max; $i++) {
            $seven = substr($digits, $i, 7);
            if (strlen($seven) === 7) {
                $out[] = substr($seven, 0, 4) . '-' . substr($seven, 4, 3);
            }
        }
        return array_values(array_unique($out));
    }
    return [];
}

$digitsOnly = preg_replace('/\D/', '', $q);
$merged = [];
$seen = [];

$base = [
    'format' => 'json',
    'addressdetails' => '1',
    'limit' => '12',
];

$ptCandidates = pt_postal_candidates($digitsOnly);
if (preg_match('/^\d{4}-\d{3}$/', str_replace(' ', '', $q))) {
    $norm = preg_replace('/\D/', '', $q);
    if (strlen($norm) === 7) {
        array_unshift($ptCandidates, substr($norm, 0, 4) . '-' . substr($norm, 4, 3));
        $ptCandidates = array_values(array_unique($ptCandidates));
    }
}

// ── 1) GEO API PT em paralelo (uma URL por hipótese)
if ($ptCandidates !== []) {
    $geoUrls = [];
    foreach ($ptCandidates as $ptCode) {
        if (preg_match('/^\d{4}-\d{3}$/', $ptCode)) {
            $geoUrls[] = 'https://json.geoapi.pt/cp/' . rawurlencode($ptCode);
        }
    }
    $geoUrls = array_values(array_unique($geoUrls));
    $bodies = curl_multi_get($geoUrls);
    foreach ($bodies as $body) {
        if ($body === false) {
            continue;
        }
        $g = parse_geoapi_body($body);
        if ($g === null) {
            continue;
        }
        $uid = 'geoapi|' . ($g['postal'] ?? '') . '|' . ($g['city'] ?? '');
        if (isset($seen[$uid])) {
            continue;
        }
        $seen[$uid] = true;
        $merged[] = $g;
    }
}

// Resultado oficial PT: não gastar segundos no Nominatim
if (count($merged) > 0) {
    echo json_encode(['ok' => true, 'results' => $merged], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 2) Nominatim: no máximo 4 URLs em paralelo (prioridade: CP PT + país, depois texto)
$nomiUrls = [];
$seenU = [];

$addN = function (array $params) use (&$nomiUrls, &$seenU, $base) {
    $p = array_merge($base, $params);
    $u = nominatim_url($p);
    if (isset($seenU[$u])) {
        return;
    }
    $seenU[$u] = true;
    $nomiUrls[] = $u;
};

$addN(['postalcode' => $q]);
if ($digitsOnly !== '' && $digitsOnly !== $q) {
    $addN(['postalcode' => $digitsOnly]);
}
foreach (array_slice($ptCandidates, 0, 3) as $ptCode) {
    $addN(['postalcode' => $ptCode, 'countrycodes' => 'pt']);
}
$nomiUrls = array_slice(array_values(array_unique($nomiUrls)), 0, 4);

$bodiesN = curl_multi_get($nomiUrls);
foreach ($bodiesN as $body) {
    if ($body === false) {
        continue;
    }
    $res = json_decode($body, true);
    if (!is_array($res)) {
        continue;
    }
    foreach ($res as $item) {
        if (!is_array($item)) {
            continue;
        }
        $m = map_item($item);
        $uid = ($item['place_id'] ?? '') . '|n|' . ($m['postal'] ?? '') . '|' . ($m['city'] ?? '');
        if (isset($seen[$uid])) {
            continue;
        }
        $seen[$uid] = true;
        $merged[] = $m;
    }
}

if (count($merged) === 0) {
    $fb = array_filter([
        $q . ', Portugal',
        $digitsOnly !== '' ? $digitsOnly . ', Portugal' : null,
        !empty($ptCandidates[0]) ? $ptCandidates[0] . ', Portugal' : null,
    ]);
    foreach (array_unique($fb) as $fq) {
        $res = http_get_json(nominatim_url(array_merge($base, ['q' => $fq])), ['Accept-Language: pt,en']);
        if ($res === null) {
            continue;
        }
        foreach ($res as $item) {
            if (!is_array($item)) {
                continue;
            }
            $m = map_item($item);
            $addr = $item['address'] ?? [];
            $cc = isset($addr['country_code']) ? strtoupper((string)$addr['country_code']) : '';
            if ($cc !== '' && $cc !== 'PT') {
                continue;
            }
            $uid = ($item['place_id'] ?? '') . '|fb|' . $m['label'];
            if (isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $merged[] = $m;
        }
        if (count($merged) > 0) {
            break;
        }
    }
}

if (count($merged) === 0) {
    $res = http_get_json(nominatim_url(array_merge($base, ['q' => $q])), ['Accept-Language: pt,en']);
    if ($res !== null) {
        foreach ($res as $item) {
            if (!is_array($item)) {
                continue;
            }
            $m = map_item($item);
            $uid = ($item['place_id'] ?? '') . '|q|' . $m['label'];
            if (isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $merged[] = $m;
        }
    }
}

echo json_encode(['ok' => true, 'results' => $merged], JSON_UNESCAPED_UNICODE);
