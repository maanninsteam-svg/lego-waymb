<?php
/**
 * Centralizador de Preços e Configurações.
 */

function _central_config() {
    static $config = null;
    if ($config === null) {
        $path = __DIR__ . '/central_config.json';
        $config = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
    }
    return $config;
}

function central_price($stage, $default = 0) {
    $config = _central_config();
    if (isset($config['prices'][$stage])) {
        return (float) $config['prices'][$stage];
    }
    return (float) $default;
}

/**
 * Retorna a URL de redirect associada a uma chave (ex: 'up1_entry' → '/up1').
 * Configurável em central_config.json → redirects. Fallback para o $default informado.
 */
function central_redirect($key, $default = '/') {
    $config = _central_config();
    if (isset($config['redirects'][$key]) && is_string($config['redirects'][$key]) && $config['redirects'][$key] !== '') {
        return (string) $config['redirects'][$key];
    }
    return (string) $default;
}
